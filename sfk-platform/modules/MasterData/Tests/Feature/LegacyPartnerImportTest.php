<?php

/*
 * Taking over 01. 取引先マスタ（得意先・仕入先） (SYSTEM_BRAIN APP_01, legacy app 6) from its record export: legacy
 * option values (incl. the live-only ones and the disabled グループ名, C-12), 得意先ID kept, users of 自社担当者 /
 * 作成者 / 更新者, re-runs and what is reported.
 */

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Models\RecordVersion;
use Modules\MasterData\Application\Partners\LegacyPartnerImport;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Models\Partner;

/** One exported record (field code => value), like the legacy export. */
function legacyPartnerRecord(int $id, array $values = []): array
{
    $values = [
        '得意先ID' => (string) $id,
        '取引先コード' => sprintf('%06d', $id),
        '取引先名' => "株式会社テスト{$id}",
        '取引先名_ふりがな' => "かぶしきがいしゃてすと{$id}",
        '略名' => "テスト{$id}                    ",
        '取引先カテゴリ' => ['得意先'],
        '郵便番号_得意先_' => '540-0001',
        '住所_得意先_' => '大阪市中央区1-1',
        'TEL_得意先_' => '06-1111-2222',
        'FAX_得意先_' => '            ',
        '請求書送付先' => '得意先名',
        '納品書送付先' => '得意先名',
        '請求締日' => '末日',
        '端数区分' => '切捨',
        '仕入カテゴリ' => null,
        '仕入先住所も同様' => [],
        '電子請求' => [],
        '回収区分' => [],
        '契約書等添付' => [],
        '自社担当者' => [],
        '金額_以上の金額_' => '0',
        'サイト' => '',
        '作成者' => ['code' => 'kikuchi@s-f-k.co.jp', 'name' => '【10】菊池 毅'],
        '更新者' => ['code' => 'kikuchi@s-f-k.co.jp', 'name' => '【10】菊池 毅'],
        '作成日時' => '2024-05-06T13:13:00Z',
        '更新日時' => '2026-08-10T06:31:00Z',
        ...$values,
    ];

    return array_map(fn (mixed $value): array => ['type' => 'X', 'value' => $value], $values);
}

/** @param  list<array<string, mixed>>  $records */
function legacyPartnerExport(array $records): array
{
    return ['app' => ['appId' => '6', 'name' => '01. 取引先マスタ（得意先・仕入先）'], 'fields' => [], 'records' => $records];
}

it('takes over the legacy partners with their 得意先ID, options and texts', function () {
    $result = app(LegacyPartnerImport::class)->import(legacyPartnerExport([
        legacyPartnerRecord(2, [
            '取引先カテゴリ' => ['得意先', '仕入先'],
            '登録番号' => 'T1-1200-0100-7205',
            '回収予定区分' => '翌月',
            '回収予定日' => '26日',
            '金額_以上の金額_' => '30',
            'サイト' => '90',
            '回収区分' => ['手形'],
            '電子請求' => ['✓'],
            '仕入カテゴリ' => '42.雑包装費',
            '納品書発行区分' => '(無)指定納品書',
            '請求書発行区分' => '(無)指定請求書',
            'グループ名' => '西日本住宅',
            '分類' => '7',
            '備考' => "初回事前振り込み\n",
        ]),
        legacyPartnerRecord(15, ['取引先名' => 'カルメル会修道院']),
        legacyPartnerRecord(16, ['取引先名' => 'カルメル会修道院 ']),
    ]));

    expect($result->created)->toBe(3)->and($result->updated)->toBe(0);

    $partner = Partner::query()->findOrFail(2);
    expect($partner->legacy_id)->toBe(2)
        ->and($partner->code)->toBe('000002')
        ->and($partner->name_kana)->toBe('かぶしきがいしゃてすと2')
        ->and($partner->short_name)->toBe('テスト2')
        ->and($partner->fax)->toBeNull()
        ->and($partner->is_customer)->toBeTrue()
        ->and($partner->is_supplier)->toBeTrue()
        ->and($partner->getAttribute('registration_number'))->toBe('T1120001007205')
        ->and($partner->getAttribute('collection_day'))->toBe('26日')
        ->and($partner->getAttribute('bill_threshold_man'))->toBe(30)
        ->and($partner->getAttribute('bill_site_days'))->toBe(90)
        ->and($partner->getAttribute('collects_by_bill'))->toBeTrue()
        ->and($partner->getAttribute('electronic_billing'))->toBeTrue()
        ->and($partner->getAttribute('purchase_category')->value)->toBe('42.雑包装費')
        ->and($partner->getAttribute('delivery_note_issuance')->value)->toBe('(無)指定納品書')
        ->and($partner->getAttribute('invoice_issuance')->value)->toBe('(無)指定請求書')
        ->and($partner->getAttribute('partner_group'))->toBe(PartnerGroup::NishiNihonJutaku)
        ->and($partner->getAttribute('notes'))->toBe('初回事前振り込み')
        ->and($partner->closing_day?->value)->toBe('末日')
        ->and($partner->rounding?->value)->toBe('切捨')
        ->and($partner->getAttribute('created_at')->format('Y-m-d H:i'))->toBe('2024-05-06 22:13');

    // 取引先名 that differ only by a trailing space stay apart, like in the legacy app (BR-MD-01)
    expect(Partner::query()->findOrFail(16)->name)->toBe('カルメル会修道院 ');

    // 取引先コード and new 得意先ID continue after the legacy ones
    expect(app(DocumentNumberGenerator::class)->peek(Partner::CODE_SEQUENCE))->toBe('000017');
    $new = Partner::query()->create(['name' => '新しい取引先', 'short_name' => '新', 'is_customer' => true]);
    expect($new->id)->toBeGreaterThan(16)->and($new->code)->toBe('000017');
});

it('creates the users of 自社担当者 / 作成者 / 更新者, who cannot sign in until a password is issued', function () {
    $result = app(LegacyPartnerImport::class)->import(legacyPartnerExport([
        legacyPartnerRecord(2, [
            '自社担当者' => [['code' => 'kojima@s-f-k.co.jp', 'name' => '【07】小嶋　昌一'], ['code' => 'youiti@s-f-k.co.jp', 'name' => '【02】社長 白石　陽一']],
            '更新者' => ['code' => 'Administrator', 'name' => 'Administrator'],
        ]),
        legacyPartnerRecord(3, ['自社担当者' => [['code' => 'kojima@s-f-k.co.jp', 'name' => '【07】小嶋　昌一']]]),
    ]));

    expect($result->usersCreated)->toBe(3);

    $kojima = User::query()->where('email', 'kojima@s-f-k.co.jp')->sole();
    expect($kojima->name)->toBe('小嶋　昌一')
        ->and($kojima->sales_staff_code)->toBe('07')
        ->and($kojima->getAttribute('last_name'))->toBe('小嶋')
        ->and($kojima->getAttribute('first_name'))->toBe('昌一')
        ->and($kojima->user_type->value)->toBe('employee')
        ->and($kojima->must_change_password)->toBeTrue()
        ->and($kojima->dashboards ?? [])->toBe([]);

    $president = User::query()->where('email', 'youiti@s-f-k.co.jp')->sole();
    expect($president->name)->toBe('白石　陽一')->and($president->position?->name)->toBe('社長');

    $partner = Partner::query()->findOrFail(2);
    expect($partner->staff->pluck('email')->sort()->values()->all())->toBe(['kojima@s-f-k.co.jp', 'youiti@s-f-k.co.jp'])
        ->and($partner->creator?->email)->toBe('kikuchi@s-f-k.co.jp')
        ->and($partner->updated_by)->toBeNull();

    $this->post('/login', ['email' => 'kojima@s-f-k.co.jp', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('updates the partners on a re-run, by 得意先ID, and starts their 変更履歴 once', function () {
    $import = app(LegacyPartnerImport::class);
    $import->import(legacyPartnerExport([legacyPartnerRecord(5, ['TEL_得意先_' => '06-0000-0001'])]));

    $version = RecordVersion::query()->sole();
    expect($version->version)->toBe(1)
        ->and($version->event)->toBe('created')
        ->and($version->user?->email)->toBe('kikuchi@s-f-k.co.jp')
        ->and($version->created_at?->format('Y-m-d H:i'))->toBe('2026-08-10 15:31');

    $again = $import->import(legacyPartnerExport([legacyPartnerRecord(5, ['TEL_得意先_' => '06-0000-0002'])]));

    expect($again->created)->toBe(0)->and($again->updated)->toBe(1)->and($again->usersCreated)->toBe(0)
        ->and(Partner::query()->count())->toBe(1)
        ->and(Partner::query()->findOrFail(5)->phone)->toBe('06-0000-0002')
        ->and(RecordVersion::query()->orderBy('version')->pluck('changed')->last())->toBe(['phone']);
});

it('reports what it could not take over', function () {
    $result = app(LegacyPartnerImport::class)->import(legacyPartnerExport([
        legacyPartnerRecord(7, [
            '請求締日' => '3日',
            '登録番号' => '102163',
            '契約書等添付' => [['fileKey' => 'x', 'name' => '契約書.pdf']],
            '仕入先住所も同様' => ['✓'],
            '住所_仕入先_' => '別の住所',
        ]),
    ]));

    $partner = Partner::query()->findOrFail(7);
    expect($partner->closing_day)->toBeNull()
        ->and($partner->getAttribute('supplier_address'))->toBe('別の住所')
        ->and($result->warnings)->toContain('得意先ID 7: 請求締日「3日」は選択肢にないため空欄にしました')
        ->and($result->warnings)->toContain('得意先ID 7: 登録番号「102163」は T + 13桁 ではありません')
        ->and($result->warnings)->toContain('得意先ID 7: 添付ファイルは移行されません（契約書等添付: 契約書.pdf）')
        ->and(implode("\n", $result->warnings))->toContain('住所(仕入先) が得意先と異なります');
});

it('refuses exports of other apps and runs from the command', function () {
    expect(fn () => app(LegacyPartnerImport::class)->import(['app' => ['appId' => '7'], 'records' => []]))
        ->toThrow(InvalidArgumentException::class);

    $file = tempnam(sys_get_temp_dir(), 'partners');
    file_put_contents($file, json_encode(legacyPartnerExport([legacyPartnerRecord(9)])));

    expect(Artisan::call('master-data:import-partners', ['file' => $file]))->toBe(0)
        ->and(Artisan::output())->toContain('Partners created: 1 / updated: 0 / users created: 1')
        ->and(Partner::query()->whereKey(9)->exists())->toBeTrue();

    unlink($file);
});

it('keeps disabled グループ名 on their records but offers only the active ones (C-12)', function () {
    expect(PartnerGroup::activeOptions())->toHaveCount(8)->not->toHaveKey('西日本住宅')
        ->and(PartnerGroup::activeOptions('西日本住宅'))->toHaveCount(9)->toHaveKey('西日本住宅')
        ->and(PartnerGroup::retiredValues())->toHaveCount(9);
});
