<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Tables;

use App\Models\User;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Billing\CollectionDay;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxCalculationUnit;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Domain\Partners\CustomerClassification;
use Modules\MasterData\Domain\Partners\CustomerStatus;
use Modules\MasterData\Domain\Partners\DocumentIssuance;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Domain\Partners\PartnerSegment;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Filament\Resources\Partners\PartnerLayout;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

/**
 * Record list of 01. 取引先マスタ. The columns shown and their order are those of the chosen 一覧 (AppListRecords::
 * viewColumns()); the legacy view 「取引先一覧」 (SYSTEM_BRAIN APP_01 views.md: its columns, sorted by 取引先コード) is
 * the app's default view, （すべて） shows columns() in order. The search box replaces the 「取引先一覧(検索特化)」 plugin
 * view. filterFields() are the fields of 絞り込む and of the 一覧 settings; inlineFields() those インライン編集 changes
 * (every field but 取引先コード, the attachments and the system fields), with the rules of PartnerForm.
 */
final class PartnersTable
{
    public const VIEW_LIST = 'list';

    public const VIEW_ALL = ListView::ALL;

    /** Columns of the legacy view 取引先一覧, in order. */
    public const LIST_COLUMNS = [
        'code', 'legacy_customer_code', 'name', 'name_kana', 'short_name', 'contact_person', 'staff', 'roles', 'partner_group',
        'purchase_category', 'closing_day', 'collection_month', 'collection_day', 'phone', 'fax',
    ];

    /** Further columns of （すべて）. */
    public const ALL_ONLY_COLUMNS = [
        'id', 'parent_company', 'customer_classification', 'customer_status', 'invoice_recipient',
        'delivery_note_recipient', 'registration_number', 'contact_note', 'tax_mode', 'rounding', 'tax_calculation_unit',
        'invoice_issuance', 'delivery_note_issuance', 'electronic_billing', 'collects_by_bill', 'bill_threshold_man',
        'bill_site_days', 'notes', 'parent_postal_code', 'parent_address', 'parent_phone', 'parent_fax', 'postal_code',
        'address', 'segment', 'supplier_same_address', 'supplier_tax_mode', 'supplier_rounding',
        'supplier_tax_calculation_unit', 'supplier_contact_person', 'supplier_postal_code', 'supplier_address',
        'supplier_phone', 'supplier_fax', 'supplier_notes', 'created_at', 'updated_at',
    ];

    /**
     * Every column (name => label), in the order of （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach ([...self::LIST_COLUMNS, ...self::ALL_ONLY_COLUMNS] as $name) {
            $columns[$name] = PartnerLayout::label($name);
        }

        return $columns;
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['staff.employeeCategories', 'staff.position']))
            ->columns([
                IconColumn::make('record')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('code')
            ->recordActions(AppRecordActions::make(PartnerResource::class))
            ->emptyStateIcon(Heroicon::OutlinedBuildingStorefront)
            ->emptyStateHeading('取引先がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_01 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => PartnerLayout::label($name);
        $text = fn (string $name): FilterField => FilterField::text($name, $label($name));
        /** @param array<string, string> $options */
        $choice = fn (string $name, array $options): FilterField => FilterField::choice($name, $label($name), $options);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());
        $rounding = [
            RoundingMode::HalfUp->value => RoundingMode::HalfUp->label(),
            RoundingMode::RoundUp->value => RoundingMode::RoundUp->label(),
            RoundingMode::Truncate->value => RoundingMode::Truncate->label(),
        ];

        return [
            FilterField::number('id', $label('id')),
            $text('code'),
            $text('legacy_customer_code'),
            $text('parent_company'),
            $text('name'),
            $text('name_kana'),
            $text('short_name'),
            $text('registration_number'),
            FilterField::choice('roles', $label('roles'), PartnerRole::options())->query(self::filterRoles(...)),
            $choice('customer_status', CustomerStatus::options()),
            $choice('customer_classification', CustomerClassification::options()),
            $choice('partner_group', PartnerGroup::options()),
            $choice('segment', PartnerSegment::options()),
            $choice('purchase_category', PurchaseCategory::options()),
            $choice('invoice_recipient', DocumentRecipient::options()),
            $choice('delivery_note_recipient', DocumentRecipient::options()),
            $text('contact_person'),
            FilterField::user('staff', $label('staff'))->relationship('staff', 'id'),
            FilterField::textarea('contact_note', $label('contact_note')),
            $choice('tax_mode', TaxMode::options()),
            $choice('rounding', $rounding),
            $choice('tax_calculation_unit', TaxCalculationUnit::options()),
            $choice('closing_day', array_combine($days, $days)),
            $choice('invoice_issuance', DocumentIssuance::invoiceOptions()),
            $choice('delivery_note_issuance', DocumentIssuance::deliveryNoteOptions()),
            $choice('collection_month', CollectionMonth::options()),
            $choice('collection_day', CollectionDay::options()),
            FilterField::number('bill_threshold_man', $label('bill_threshold_man')),
            FilterField::number('bill_site_days', $label('bill_site_days')),
            FilterField::textarea('notes', $label('notes')),
            $text('parent_postal_code'),
            $text('parent_address'),
            $text('parent_phone'),
            $text('parent_fax'),
            $text('postal_code'),
            $text('address'),
            $text('phone'),
            $text('fax'),
            $choice('supplier_tax_mode', TaxMode::options()),
            $choice('supplier_rounding', $rounding),
            $choice('supplier_tax_calculation_unit', TaxCalculationUnit::options()),
            $text('supplier_postal_code'),
            $text('supplier_address'),
            $text('supplier_phone'),
            $text('supplier_fax'),
            FilterField::textarea('supplier_notes', $label('supplier_notes').'(仕入先)'),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /**
     * Fields of インライン編集, checked like PartnerForm (BR-MD-01: 取引先名 unique; the legacy required fields).
     *
     * @return list<InlineField>
     */
    public static function inlineFields(): array
    {
        $text = fn (string $name, int $length = 64): InlineField => InlineField::text($name)->maxLength($length);
        $phone = fn (string $name): InlineField => InlineField::text($name)->maxLength(32);
        $postalCode = fn (string $name): InlineField => InlineField::text($name)->maxLength(8)->regex('/^\d{3}-?\d{4}$/');
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());
        $rounding = [
            RoundingMode::HalfUp->value => RoundingMode::HalfUp->label(),
            RoundingMode::RoundUp->value => RoundingMode::RoundUp->label(),
            RoundingMode::Truncate->value => RoundingMode::Truncate->label(),
        ];

        return [
            $text('legacy_customer_code'),
            $text('name')->required()->unique('partners'),
            $text('name_kana', 128)->required(),
            $text('short_name')->required(),
            $text('contact_person'),
            InlineField::users('staff'),
            InlineField::checkboxes('roles', PartnerRole::options())->required(),
            InlineField::select('partner_group', PartnerGroup::options())->retiredOptions(PartnerGroup::retiredValues()),
            InlineField::select('purchase_category', PurchaseCategory::options()),
            InlineField::select('closing_day', array_combine($days, $days)),
            InlineField::select('collection_month', CollectionMonth::options()),
            InlineField::select('collection_day', CollectionDay::options()),
            $phone('phone')->required(),
            $phone('fax'),
            $text('parent_company', 128),
            InlineField::select('customer_classification', CustomerClassification::options()),
            InlineField::select('customer_status', CustomerStatus::options()),
            InlineField::select('invoice_recipient', DocumentRecipient::options())->required(),
            InlineField::select('delivery_note_recipient', DocumentRecipient::options())->required(),
            InlineField::text('registration_number')->maxLength(14)->regex('/^T\d{13}$/'),
            InlineField::textarea('contact_note'),
            InlineField::select('tax_mode', TaxMode::options()),
            InlineField::select('rounding', $rounding),
            InlineField::select('tax_calculation_unit', TaxCalculationUnit::options()),
            InlineField::select('invoice_issuance', DocumentIssuance::invoiceOptions()),
            InlineField::select('delivery_note_issuance', DocumentIssuance::deliveryNoteOptions()),
            InlineField::checkbox('electronic_billing'),
            InlineField::checkbox('collects_by_bill', '手形'),
            InlineField::number('bill_threshold_man')->minValue(0),
            InlineField::number('bill_site_days')->minValue(0)->maxValue(365),
            InlineField::textarea('notes'),
            $postalCode('parent_postal_code'),
            $text('parent_address', 255),
            $phone('parent_phone'),
            $phone('parent_fax'),
            $postalCode('postal_code')->required(),
            $text('address', 255)->required(),
            InlineField::select('segment', PartnerSegment::options()),
            InlineField::checkbox('supplier_same_address'),
            InlineField::select('supplier_tax_mode', TaxMode::options()),
            InlineField::select('supplier_rounding', $rounding),
            InlineField::select('supplier_tax_calculation_unit', TaxCalculationUnit::options()),
            $text('supplier_contact_person'),
            $postalCode('supplier_postal_code'),
            $text('supplier_address', 255),
            $phone('supplier_phone'),
            $phone('supplier_fax'),
            InlineField::textarea('supplier_notes'),
        ];
    }

    /**
     * 取引先カテゴリ is stored as three flags (is_customer, is_supplier, is_other; BR-MD-02); "--" = none of them.
     *
     * @param  Builder<*>  $query
     */
    private static function filterRoles(Builder $query, FilterCondition $condition): void
    {
        $columns = [];
        foreach ($condition->values as $value) {
            $columns[] = match ($value) {
                PartnerRole::Customer->value => 'is_customer',
                PartnerRole::Supplier->value => 'is_supplier',
                PartnerRole::Other->value => 'is_other',
                default => null,
            };
        }
        $blank = in_array(null, $columns, true);
        $columns = array_values(array_filter($columns));

        $any = function (Builder $q) use ($columns, $blank): void {
            foreach ($columns as $column) {
                $q->orWhere($column, true);
            }
            if ($blank) {
                $q->orWhere(fn (Builder $none) => $none->where('is_customer', false)->where('is_supplier', false)->where('is_other', false));
            }
        };

        $condition->operator === FilterOperator::NotIn ? $query->whereNot($any) : $query->where($any);
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(PartnerLayout::label($name))
            ->placeholder('');

        return match ($name) {
            'name' => $column->searchable()->sortable(),
            'name_kana' => $column->searchable()->sortable(),
            'code', 'short_name', 'legacy_customer_code' => $column->searchable()->sortable(),
            'id' => $column->sortable(),
            // 昇順: 得意先, then 仕入先, then その他 (BR-MD-02 flags)
            'roles' => $column
                ->state(fn (Partner $record): array => array_map(fn (PartnerRole $role): string => $role->label(), $record->roles()))
                ->listWithLineBreaks()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy('is_customer', $direction === 'asc' ? 'desc' : 'asc')
                    ->orderBy('is_supplier', $direction === 'asc' ? 'desc' : 'asc')
                    ->orderBy('is_other', $direction === 'asc' ? 'desc' : 'asc')),
            // names open the ユーザー情報 popup, so the cell is not a link to the record
            'staff' => $column
                ->state(fn (Partner $record): HtmlString => UserPopup::list(
                    $record->staff,
                    fn (User $user): string => filled($user->sales_staff_code) ? "【{$user->sales_staff_code}】{$user->name}" : $user->name,
                ))
                ->html()
                ->disabledClick()
                // by the first 自社担当者 name
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')
                        ->join('partner_staff', 'partner_staff.user_id', '=', 'users.id')
                        ->whereColumn('partner_staff.partner_id', 'partners.id')
                        ->orderBy('users.name')
                        ->limit(1),
                    $direction,
                )),
            'electronic_billing', 'supplier_same_address' => $column->state(fn (Partner $record): string => $record->getAttribute($name) ? '✓' : ''),
            'collects_by_bill' => $column->state(fn (Partner $record): string => $record->getAttribute($name) ? '手形' : ''),
            'created_at', 'updated_at' => $column->dateTime('Y-m-d H:i')->sortable(),
            default => $column->formatStateUsing(fn (mixed $state): string => $state instanceof BackedEnum ? (string) $state->value : (string) $state),
        };
    }
}
