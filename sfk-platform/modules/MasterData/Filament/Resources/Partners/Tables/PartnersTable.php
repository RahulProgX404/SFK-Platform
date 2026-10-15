<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Tables;

use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Filament\Resources\Partners\PartnerLayout;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

/**
 * Record list of 01. 取引先マスタ with the legacy views (SYSTEM_BRAIN APP_01 views.md, chosen on ListPartners):
 * 「取引先一覧」 (its 14 columns, sorted by 取引先コード) and 「（すべて）」 (every field). The search box replaces the
 * 「取引先一覧(検索特化)」 plugin view.
 */
final class PartnersTable
{
    public const VIEW_LIST = 'list';

    public const VIEW_ALL = 'all';

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

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('staff'))
            ->columns([
                IconColumn::make('record')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->width('2.5rem'),
                ...array_map(fn (string $name): TextColumn => self::column($name, inAll: false), self::LIST_COLUMNS),
                ...array_map(fn (string $name): TextColumn => self::column($name, inAll: true), self::ALL_ONLY_COLUMNS),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('role')
                    ->label('取引先カテゴリ')
                    ->options(PartnerRole::options())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        PartnerRole::Customer->value => $query->where('is_customer', true),
                        PartnerRole::Supplier->value => $query->where('is_supplier', true),
                        PartnerRole::Other->value => $query->where('is_other', true),
                        default => $query,
                    }),
                SelectFilter::make('closing_day')
                    ->label('請求締日')
                    ->options(array_combine(
                        array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
                        array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
                    )),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->visible(fn (Partner $record): bool => PartnerResource::canEdit($record)),
                DeleteAction::make()->iconButton()->visible(fn (Partner $record): bool => PartnerResource::canDelete($record)),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBuildingStorefront)
            ->emptyStateHeading('取引先がありません');
    }

    private static function column(string $name, bool $inAll): TextColumn
    {
        $column = TextColumn::make($name)
            ->label(PartnerLayout::label($name))
            ->placeholder('')
            ->visible(fn (Component $livewire): bool => ! $inAll || ($livewire->listView ?? null) === self::VIEW_ALL);

        return match ($name) {
            'name' => $column->weight(FontWeight::SemiBold)->searchable()->sortable(),
            'name_kana' => $column->searchable()->sortable(),
            'code', 'short_name', 'legacy_customer_code' => $column->searchable()->sortable(),
            'id' => $column->sortable(),
            'roles' => $column
                ->state(fn (Partner $record): array => array_map(fn (PartnerRole $role): string => $role->label(), $record->roles()))
                ->listWithLineBreaks(),
            'staff' => $column
                ->state(fn (Partner $record): array => $record->staff
                    ->map(fn (User $user): string => filled($user->sales_staff_code) ? "【{$user->sales_staff_code}】{$user->name}" : $user->name)
                    ->all())
                ->icon(Heroicon::User)
                ->color('primary')
                ->listWithLineBreaks(),
            'electronic_billing', 'supplier_same_address' => $column->state(fn (Partner $record): string => $record->getAttribute($name) ? '✓' : ''),
            'collects_by_bill' => $column->state(fn (Partner $record): string => $record->getAttribute($name) ? '手形' : ''),
            'created_at', 'updated_at' => $column->dateTime('Y-m-d H:i')->sortable(),
            default => $column->formatStateUsing(fn (mixed $state): string => $state instanceof BackedEnum ? (string) $state->value : (string) $state),
        };
    }
}
