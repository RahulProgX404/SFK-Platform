<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Partners;

use App\Models\User;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Billing\CollectionDay;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxCalculationUnit;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Models\Position;
use Modules\Core\Models\RecordVersion;
use Modules\MasterData\Domain\Partners\CustomerClassification;
use Modules\MasterData\Domain\Partners\CustomerStatus;
use Modules\MasterData\Domain\Partners\DocumentIssuance;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Domain\Partners\PartnerSegment;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;

/**
 * Takes over the records of the legacy app 6 「01. 取引先マスタ（得意先・仕入先）」 from its record export (app,
 * fields, records — e.g. seed.json). Safe to run again: partners are matched by 得意先ID (`legacy_id`), which also
 * becomes their `id` so the 得意先ID staff know stays the same; a re-run updates them to the export.
 *
 * - 取引先名 is kept exactly (incl. trailing spaces: the legacy app holds names that differ only by them, and other
 *   apps' records refer to the names); other texts are trimmed, blanks become empty.
 * - 自社担当者 / 作成者 / 更新者 are users by their legacy login (email). Missing people are created as employees:
 *   「【07】小嶋　昌一」 → 営業担当者コード 07, name 小嶋　昌一; 「【02】社長 白石　陽一」 also gets the 役職 社長. They get a
 *   random temporary password and no dashboards, so they cannot sign in until an administrator issues a password.
 * - Option values not in the app's lists are left blank and reported; 登録番号 loses its hyphens (reported when it is
 *   not T + 13 digits). The supplier side is taken as stored even with 仕入先住所も同様 (differences reported).
 * - Not taken over: attachments (only their names are in the export — reported), the address split fields (the
 *   addresses hold them), 前回支払残高 / 前回買掛残高 / フラグ (see the partners migration).
 * - 取引先コード's sequence continues after the highest code; each new partner starts its 変更履歴 with version 1
 *   (by its 更新者, at its 更新日時).
 */
final class LegacyPartnerImport
{
    /** Legacy app id of 01. 取引先マスタ (SYSTEM_BRAIN APP_01). */
    public const LEGACY_APP_ID = '6';

    private const TITLES = ['会長', '社長', '副社長', '専務', '常務'];

    /** @var array<string, User|null> legacy login => user */
    private array $users = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly DocumentNumberGenerator $numbers,
        private readonly RecordHistory $history,
    ) {}

    /** @param  array<string, mixed>  $export  the decoded export: app, fields, records */
    public function import(array $export): LegacyPartnerImportResult
    {
        $appId = (string) data_get($export, 'app.appId');
        if ($appId !== self::LEGACY_APP_ID || ! is_array($export['records'] ?? null)) {
            throw new InvalidArgumentException('01. 取引先マスタ（得意先・仕入先）のエクスポートではありません。');
        }

        $this->users = [];
        $this->warnings = [];
        $usersBefore = User::query()->count();
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($export, &$created, &$updated): void {
            foreach ($export['records'] as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $this->importRecord($record) ? $created++ : $updated++;
            }

            $this->numbers->ensureNextAtLeast(Partner::CODE_SEQUENCE, $this->highestNumericCode() + 1);
            $this->syncIdSequence();
        });

        return new LegacyPartnerImportResult($created, $updated, User::query()->count() - $usersBefore, $this->warnings);
    }

    /** @param  array<string, mixed>  $record  one exported record: field code => {type, value}; true when created */
    private function importRecord(array $record): bool
    {
        $legacyId = (int) self::value($record, '得意先ID');
        $label = '得意先ID '.$legacyId;
        $attributes = $this->attributes($record, $label);

        $existing = Partner::query()->where('legacy_id', $legacyId)->first();
        if ($existing !== null) {
            DB::table('partners')->where('id', $existing->id)->update($attributes);
            $partner = $existing;
        } else {
            $free = ! DB::table('partners')->where('id', $legacyId)->exists();
            $id = DB::table('partners')->insertGetId([...($free ? ['id' => $legacyId] : []), ...$attributes]);
            $partner = Partner::query()->findOrFail($id);
        }

        $staff = array_values(array_filter(array_map(
            fn (mixed $user): ?int => is_array($user) ? $this->user($user)?->id : null,
            (array) self::value($record, '自社担当者'),
        )));
        $partner->staff()->sync($staff);

        $this->reportLeftOut($record, $label);

        if ($existing === null) {
            $partner->refresh();
            $version = $this->history->record($partner, $attributes['updated_by']);
            $version?->forceFill(['created_at' => $attributes['updated_at']])->save();

            return true;
        }

        if (RecordVersion::query()->where('record_type', $partner->getMorphClass())->where('record_id', $partner->id)->exists()) {
            $this->history->record($partner->refresh(), $attributes['updated_by']);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed> partners columns
     */
    private function attributes(array $record, string $label): array
    {
        $text = fn (string $code): ?string => self::text(self::value($record, $code));
        $roles = array_map('strval', (array) self::value($record, '取引先カテゴリ'));
        $option = fn (string $code, string $enum): ?string => $this->option($record, $code, $enum, $label);

        return [
            'legacy_id' => (int) self::value($record, '得意先ID'),
            'code' => $text('取引先コード'),
            'parent_company' => $text('親会社_グループ会社'),
            'name' => (string) self::value($record, '取引先名'),
            'name_kana' => $text('取引先名_ふりがな'),
            'short_name' => (string) $text('略名'),
            'registration_number' => $this->registrationNumber($text('登録番号'), $label),
            ...Partner::roleColumns(array_intersect($roles, array_keys(PartnerRole::options()))),
            'parent_postal_code' => $text('郵便番号_親会社_'),
            'parent_address' => $text('住所_親会社_'),
            'parent_phone' => $text('TEL_親会社_'),
            'parent_fax' => $text('FAX_親会社_'),
            'postal_code' => $text('郵便番号_得意先_'),
            'address' => $text('住所_得意先_'),
            'phone' => $text('TEL_得意先_'),
            'fax' => $text('FAX_得意先_'),
            'supplier_same_address' => self::checked($record, '仕入先住所も同様'),
            'customer_status' => $option('得意先ステータス', CustomerStatus::class),
            'customer_classification' => $option('得意先分類区分', CustomerClassification::class),
            'legacy_customer_code' => $text('旧得意先コード'),
            'partner_group' => $option('グループ名', PartnerGroup::class),
            'segment' => $this->segment($text('分類'), $label),
            'invoice_recipient' => $option('請求書送付先', DocumentRecipient::class) ?? DocumentRecipient::Customer->value,
            'delivery_note_recipient' => $option('納品書送付先', DocumentRecipient::class) ?? DocumentRecipient::Customer->value,
            'contact_person' => $text('担当者'),
            'contact_note' => $text('担当者についてのメモ'),
            'tax_mode' => $option('消費税区分', TaxMode::class),
            'rounding' => $option('端数区分', RoundingMode::class),
            'tax_calculation_unit' => $option('計算区分', TaxCalculationUnit::class),
            'closing_day' => $option('請求締日', ClosingDay::class),
            'invoice_issuance' => $this->issuance($text('請求書発行区分'), DocumentIssuance::invoiceOptions(), '請求書発行区分', $label),
            'delivery_note_issuance' => $this->issuance($text('納品書発行区分'), DocumentIssuance::deliveryNoteOptions(), '納品書発行区分', $label),
            'electronic_billing' => self::checked($record, '電子請求'),
            'collection_month' => $option('回収予定区分', CollectionMonth::class),
            'collection_day' => $this->collectionDay($text('回収予定日'), $label),
            'collects_by_bill' => self::checked($record, '回収区分'),
            'bill_threshold_man' => self::number(self::value($record, '金額_以上の金額_')),
            'bill_site_days' => self::number(self::value($record, 'サイト')),
            'notes' => $text('備考'),
            'purchase_category' => $option('仕入カテゴリ', PurchaseCategory::class),
            'supplier_tax_mode' => $option('消費税区分_仕入先_', TaxMode::class),
            'supplier_rounding' => $option('端数区分_仕入先_', RoundingMode::class),
            'supplier_tax_calculation_unit' => $option('計算区分_仕入先_', TaxCalculationUnit::class),
            'supplier_contact_person' => $text('担当者_0'),
            'supplier_postal_code' => $text('郵便番号_仕入先_'),
            'supplier_address' => $text('住所_仕入先_'),
            'supplier_phone' => $text('TEL_仕入先_'),
            'supplier_fax' => $text('FAX_仕入先_'),
            'supplier_notes' => $text('備考_0'),
            'mf_partner_id' => $text('MF取引先ID'),
            'legacy_supplier_code' => $text('旧得意先コード_仕入_'),
            'created_by' => $this->user(self::value($record, '作成者'))?->id,
            'updated_by' => $this->user(self::value($record, '更新者'))?->id,
            'created_at' => self::time(self::value($record, '作成日時')),
            'updated_at' => self::time(self::value($record, '更新日時')),
        ];
    }

    /**
     * The value of an option field when the app offers it, else blank (reported).
     *
     * @param  class-string<BackedEnum>  $enum
     * @param  array<string, mixed>  $record
     */
    private function option(array $record, string $code, string $enum, string $label): ?string
    {
        $value = self::text(self::value($record, $code));
        if ($value === null) {
            return null;
        }

        if ($enum::tryFrom($value) === null) {
            $this->warnings[] = "{$label}: {$code}「{$value}」は選択肢にないため空欄にしました";

            return null;
        }

        return $value;
    }

    /** @param  array<string, string>  $options */
    private function issuance(?string $value, array $options, string $code, string $label): ?string
    {
        if ($value !== null && ! array_key_exists($value, $options)) {
            $this->warnings[] = "{$label}: {$code}「{$value}」は選択肢にないため空欄にしました";

            return null;
        }

        return $value;
    }

    private function segment(?string $value, string $label): ?string
    {
        if ($value !== null && ! in_array($value, PartnerSegment::OPTIONS, true)) {
            $this->warnings[] = "{$label}: 分類「{$value}」は選択肢にないため空欄にしました";

            return null;
        }

        return $value;
    }

    private function collectionDay(?string $value, string $label): ?string
    {
        if ($value !== null && ! array_key_exists($value, CollectionDay::options())) {
            $this->warnings[] = "{$label}: 回収予定日「{$value}」は選択肢にないため空欄にしました";

            return null;
        }

        return $value;
    }

    /** 登録番号 without hyphens / spaces; reported when it is not T + 13 digits. */
    private function registrationNumber(?string $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = (string) preg_replace('/[\s\-‐－ー]/u', '', mb_convert_kana($value, 'as'));
        if (preg_match('/^T\d{13}$/', $number) !== 1) {
            $this->warnings[] = "{$label}: 登録番号「{$value}」は T + 13桁 ではありません";
        }

        return mb_substr($number, 0, 14);
    }

    /** @param  array<string, mixed>  $record */
    private function reportLeftOut(array $record, string $label): void
    {
        foreach (['契約書等添付', '契約書等添付_0'] as $code) {
            $files = array_filter(array_map(
                fn (mixed $file): string => is_array($file) ? (string) ($file['name'] ?? '') : '',
                (array) self::value($record, $code),
            ));
            if ($files !== []) {
                $this->warnings[] = "{$label}: 添付ファイルは移行されません（{$code}: ".implode('、', $files).'）';
            }
        }

        if (self::checked($record, '仕入先住所も同様')) {
            foreach (['郵便番号', '住所', 'TEL', 'FAX'] as $field) {
                if (self::text(self::value($record, "{$field}_得意先_")) !== self::text(self::value($record, "{$field}_仕入先_"))) {
                    $this->warnings[] = "{$label}: 仕入先住所も同様 ですが {$field}(仕入先) が得意先と異なります（次の保存で得意先の値になります）";
                }
            }
        }

        foreach (['前回支払残高', '前回買掛残高', 'フラグ'] as $code) {
            if (self::text(self::value($record, $code)) !== null) {
                $this->warnings[] = "{$label}: {$code} は移行されません";
            }
        }
    }

    /**
     * The user of a legacy login ({code, name}), created when missing; null for unknown logins (e.g. Administrator).
     */
    private function user(mixed $legacyUser): ?User
    {
        if (! is_array($legacyUser)) {
            return null;
        }

        $email = trim((string) ($legacyUser['code'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (array_key_exists($email, $this->users)) {
            return $this->users[$email];
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            [$code, $title, $name] = self::splitLegacyName((string) ($legacyUser['name'] ?? $email));
            $parts = preg_split('/[\s　]+/u', $name, 2) ?: [$name];
            $user = $this->accounts->create([
                'name' => $name,
                'email' => $email,
                'sales_staff_code' => $code !== null && ! User::query()->where('sales_staff_code', $code)->exists() ? $code : null,
                'last_name' => $parts[0],
                'first_name' => $parts[1] ?? null,
                'position_id' => $title !== null ? Position::query()->firstOrCreate(['name' => $title])->id : null,
            ])->user;
        }

        return $this->users[$email] = $user;
    }

    /**
     * 「【02】社長 白石　陽一」 → ['02', '社長', '白石　陽一'].
     *
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    public static function splitLegacyName(string $name): array
    {
        $name = trim($name);
        $code = null;
        if (preg_match('/^【([^】]+)】\s*(.*)$/u', $name, $m) === 1) {
            $code = $m[1];
            $name = $m[2];
        }

        $title = null;
        foreach (self::TITLES as $candidate) {
            if (preg_match('/^'.$candidate.'[\s　]+(.+)$/u', $name, $m) === 1) {
                $title = $candidate;
                $name = $m[1];
                break;
            }
        }

        return [$code, $title, trim($name)];
    }

    private function highestNumericCode(): int
    {
        $highest = 0;
        foreach (DB::table('partners')->pluck('code') as $code) {
            if (is_string($code) && preg_match('/^\d+$/', $code) === 1) {
                $highest = max($highest, (int) $code);
            }
        }

        return $highest;
    }

    /** Partners got their legacy 得意先ID as id: new partners continue after the highest one. */
    private function syncIdSequence(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('partners', 'id'), GREATEST((SELECT COALESCE(MAX(id), 0) FROM partners), 1))");
        }
    }

    /** @param  array<string, mixed>  $record */
    private static function value(array $record, string $code): mixed
    {
        $field = $record[$code] ?? null;

        return is_array($field) ? ($field['value'] ?? null) : null;
    }

    /** @param  array<string, mixed>  $record */
    private static function checked(array $record, string $code): bool
    {
        return array_filter((array) self::value($record, $code), fn (mixed $item): bool => filled($item)) !== [];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        // white space incl. the full-width space (trim() works on bytes and would cut Japanese characters)
        $value = (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', (string) $value);

        return $value === '' ? null : $value;
    }

    private static function number(mixed $value): ?int
    {
        $value = self::text($value);

        return $value !== null && is_numeric($value) ? (int) round((float) $value) : null;
    }

    private static function time(mixed $value): ?string
    {
        $value = self::text($value);

        return $value !== null ? Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') : null;
    }
}
