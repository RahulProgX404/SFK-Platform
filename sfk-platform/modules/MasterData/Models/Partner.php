<?php

declare(strict_types=1);

namespace Modules\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxCalculationUnit;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\MasterData\Domain\Partners\CustomerClassification;
use Modules\MasterData\Domain\Partners\CustomerStatus;
use Modules\MasterData\Domain\Partners\DocumentIssuance;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Domain\Partners\PurchaseCategory;

/**
 * 取引先 (SYSTEM_BRAIN APP_01): customer, supplier and/or other in one record (BR-MD-02), with customer-side and
 * supplier-side terms. Transactions will reference `id` (not the name, unlike the legacy lookups) and snapshot
 * only what documents must freeze. コメント notify 作成者 and 更新者 (legacy app notification on 「コメントの書き込み」,
 * SYSTEM_BRAIN APP_01 permissions.md).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $code
 * @property string $name
 * @property string|null $name_kana
 * @property string $short_name
 * @property string|null $parent_company
 * @property bool $is_customer
 * @property bool $is_supplier
 * @property bool $is_other
 * @property bool $supplier_same_address
 * @property DocumentRecipient $invoice_recipient
 * @property DocumentRecipient $delivery_note_recipient
 * @property RoundingMode|null $rounding
 * @property RoundingMode|null $supplier_rounding
 * @property ClosingDay|null $closing_day
 * @property PurchaseCategory|null $purchase_category
 * @property TaxMode|null $supplier_tax_mode
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class Partner extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments;
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    /** Address fields copied to the supplier side when 「仕入先住所も同様」 is checked. */
    private const SUPPLIER_ADDRESS = [
        'postal_code' => 'supplier_postal_code',
        'address' => 'supplier_address',
        'phone' => 'supplier_phone',
        'fax' => 'supplier_fax',
    ];

    /** core.sequences key of 取引先コード. */
    public const CODE_SEQUENCE = 'partner';

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected $attributes = [
        'invoice_recipient' => '得意先名',
        'delivery_note_recipient' => '得意先名',
    ];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'is_other' => 'boolean',
            'supplier_same_address' => 'boolean',
            'electronic_billing' => 'boolean',
            'collects_by_bill' => 'boolean',
            'bill_threshold_man' => 'integer',
            'bill_site_days' => 'integer',
            'legacy_id' => 'integer',
            'contract_files' => 'array',
            'supplier_contract_files' => 'array',
            'customer_status' => CustomerStatus::class,
            'customer_classification' => CustomerClassification::class,
            'partner_group' => PartnerGroup::class,
            'invoice_recipient' => DocumentRecipient::class,
            'delivery_note_recipient' => DocumentRecipient::class,
            'tax_mode' => TaxMode::class,
            'rounding' => RoundingMode::class,
            'tax_calculation_unit' => TaxCalculationUnit::class,
            'closing_day' => ClosingDay::class,
            'invoice_issuance' => DocumentIssuance::class,
            'delivery_note_issuance' => DocumentIssuance::class,
            'collection_month' => CollectionMonth::class,
            'purchase_category' => PurchaseCategory::class,
            'supplier_tax_mode' => TaxMode::class,
            'supplier_rounding' => RoundingMode::class,
            'supplier_tax_calculation_unit' => TaxCalculationUnit::class,
        ];
    }

    protected static function booted(): void
    {
        // 取引先コード: next free 6-digit number (BR-MD-01 unique); codes kept from the legacy data are skipped.
        self::creating(function (self $partner): void {
            if (blank($partner->code)) {
                $numbers = app(DocumentNumberGenerator::class);
                do {
                    $code = $numbers->next(self::CODE_SEQUENCE);
                } while (self::query()->where('code', $code)->exists());
                $partner->code = $code;
            }
        });

        self::saving(function (self $partner): void {
            if ($partner->supplier_same_address) {
                foreach (self::SUPPLIER_ADDRESS as $from => $to) {
                    $partner->setAttribute($to, $partner->getAttribute($from));
                }
            }

            $userId = auth()->id();
            if ($userId !== null) {
                $partner->updated_by = (int) $userId;
                if (! $partner->exists) {
                    $partner->created_by = (int) $userId;
                }
            }
        });
    }

    /** @return list<PartnerRole> */
    public function roles(): array
    {
        return array_values(array_filter([
            $this->is_customer ? PartnerRole::Customer : null,
            $this->is_supplier ? PartnerRole::Supplier : null,
            $this->is_other ? PartnerRole::Other : null,
        ]));
    }

    /**
     * 取引先カテゴリ selection → role columns.
     *
     * @param  array<array-key, mixed>  $roles  role values (得意先 / 仕入先 / その他)
     * @return array{is_customer: bool, is_supplier: bool, is_other: bool}
     */
    public static function roleColumns(array $roles): array
    {
        $roles = array_map(fn (mixed $role): string => $role instanceof PartnerRole ? $role->value : (string) $role, $roles);

        return [
            'is_customer' => in_array(PartnerRole::Customer->value, $roles, true),
            'is_supplier' => in_array(PartnerRole::Supplier->value, $roles, true),
            'is_other' => in_array(PartnerRole::Other->value, $roles, true),
        ];
    }

    /**
     * Where invoices go (BR-MD-06): the parent company when so routed and it has an address, else the partner.
     *
     * @return array{name: string, postal_code: string|null, address: string|null}
     */
    public function invoiceAddressee(): array
    {
        return $this->addressee($this->invoice_recipient);
    }

    /** @return array{name: string, postal_code: string|null, address: string|null} */
    public function deliveryNoteAddressee(): array
    {
        return $this->addressee($this->delivery_note_recipient);
    }

    /** @return array{name: string, postal_code: string|null, address: string|null} */
    private function addressee(DocumentRecipient $recipient): array
    {
        if ($recipient === DocumentRecipient::ParentCompany && filled($this->parent_company)) {
            return [
                'name' => (string) $this->parent_company,
                'postal_code' => $this->getAttribute('parent_postal_code'),
                'address' => $this->getAttribute('parent_address'),
            ];
        }

        return ['name' => $this->name, 'postal_code' => $this->getAttribute('postal_code'), 'address' => $this->getAttribute('address')];
    }

    /**
     * Partners offered where a customer is chosen (orders also allow その他 — BR-MD-02).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('is_customer', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->where('is_supplier', true);
    }

    /**
     * 変更履歴: the columns, with 取引先カテゴリ as `roles` (instead of the three flags) and 自社担当者 as `staff` (user
     * ids).
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array
    {
        $snapshot = $this->columnSnapshot();
        unset($snapshot['is_customer'], $snapshot['is_supplier'], $snapshot['is_other']);
        $snapshot['roles'] = array_map(fn (PartnerRole $role): string => $role->value, $this->roles());
        $snapshot['staff'] = $this->staff()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = $this->columnPreview($snapshot);
        $copy->forceFill(self::roleColumns((array) ($snapshot['roles'] ?? [])));
        $copy->setRelation('staff', User::query()->whereIn('id', (array) ($snapshot['staff'] ?? []))->orderBy('name')->get());

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        $this->forceFill(self::roleColumns((array) ($snapshot['roles'] ?? [])));
        $this->restoreColumns($snapshot);
        $this->staff()->sync(User::query()->whereIn('id', (array) ($snapshot['staff'] ?? []))->pluck('id')->all());
        $this->unsetRelation('staff');
    }

    /** @return list<User|null> 作成者 and 更新者 */
    public function commentRecipients(): iterable
    {
        return [$this->creator, $this->updater];
    }

    /**
     * 自社担当者 (legacy user selection, several allowed).
     *
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_staff')->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
