<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes\Tables;

use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteLayout;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\DeliveryNote;

/**
 * Record list of 10. 納品書管理(売上) with the legacy views (SYSTEM_BRAIN APP_10 views.md): 納品書管理一覧 and one list per
 * 請求締日, newest first; （すべて） shows columns() in order. The test view 「Sample（納品日：…）」 is not carried over;
 * the search box replaces 納品書一覧(検索特化). インライン編集 changes 送付, 請求予定日 and 備考.
 */
final class DeliveryNotesTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['closing_day', 'number', 'delivery_note_issuance', 'send_status', 'sold_on', 'customer_id', 'subtotal', 'pdf_files'];

    private const CLOSING_COLUMNS = ['created_at', 'number', 'delivery_note_issuance', 'send_status', 'sold_on', 'customer_id', 'subtotal'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'send_status', 'sold_on', 'customer_id', 'assignees', 'number', 'registration_number', 'subtotal', 'tax_total', 'notes', 'pdf_files',
            'customer_code', 'billing_due_on', 'postal_code', 'address', 'closing_day', 'delivery_note_issuance', 'invoice_issuance', 'created_at'] as $name) {
            $columns[$name] = DeliveryNoteLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        $byId = [['field' => 'id', 'direction' => 'desc']];
        $views = [ListView::table(self::VIEW_LIST, '納品書管理一覧', self::LIST_COLUMNS, $byId)];

        foreach (ClosingDay::cases() as $day) {
            $views[] = ListView::table(
                'closing-'.$day->name,
                $day === ClosingDay::AdHoc ? '随時請求一覧' : $day->value.'締日一覧',
                $day === ClosingDay::AdHoc ? array_values(array_diff(self::CLOSING_COLUMNS, ['delivery_note_issuance'])) : self::CLOSING_COLUMNS,
                $byId,
                [['field' => 'closing_day', 'operator' => FilterOperator::In->value, 'values' => [$day->value]]],
            );
        }

        return $views;
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'assignees']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(DeliveryNoteResource::class))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('納品書がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_10 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => DeliveryNoteLayout::label($name);
        $days = array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases());

        return [
            FilterField::choice('send_status', $label('send_status'), SaleChoices::options(SaleChoices::SEND_STATUSES)),
            FilterField::text('registration_number', $label('registration_number')),
            FilterField::text('number', $label('number')),
            FilterField::date('sold_on', $label('sold_on')),
            FilterField::choice('customer_id', $label('customer_id'), fn (): array => Partner::query()->where('is_customer', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all()),
            FilterField::user('assignees', $label('assignees'))->relationship('assignees', 'id'),
            FilterField::date('billing_due_on', $label('billing_due_on')),
            FilterField::text('postal_code', $label('postal_code')),
            FilterField::text('address', $label('address')),
            FilterField::number('subtotal', $label('subtotal')),
            FilterField::number('tax_total', $label('tax_total')),
            FilterField::textarea('notes', $label('notes')),
            FilterField::choice('closing_day', $label('closing_day'), array_combine($days, $days)),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [
            InlineField::select('send_status', SaleChoices::options(SaleChoices::SEND_STATUSES))->required(),
            InlineField::date('billing_due_on'),
            InlineField::textarea('notes'),
        ];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(DeliveryNoteLayout::label($name))->placeholder('');
        $partner = fn (string $attribute): \Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            Partner::query()->select("partners.{$attribute}")->whereColumn('partners.id', 'delivery_notes.customer_id'),
            $direction,
        );

        return match ($name) {
            'number', 'address', 'notes' => $column->searchable(),
            'sold_on', 'billing_due_on' => $column->date('Y-m-d'),
            'created_at' => $column->dateTime('Y-m-d H:i'),
            'subtotal', 'tax_total' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            'customer_id' => $column
                ->state(fn (DeliveryNote $record): ?string => $record->customer?->name)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('customer', fn (Builder $q): Builder => $q->whereLike('name', "%{$search}%")))
                ->sortable(query: $partner('name')),
            'customer_code' => $column->state(fn (DeliveryNote $record): ?string => $record->customer?->code)->sortable(query: $partner('code')),
            'delivery_note_issuance', 'invoice_issuance' => $column
                ->state(fn (DeliveryNote $record): ?string => SalesFormat::text($record->customer?->getAttribute($name)))
                ->sortable(query: $partner($name)),
            'pdf_files' => $column->state(fn (DeliveryNote $record): ?string => implode('、', array_map('basename', (array) $record->pdf_files)) ?: null),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            'assignees' => $column
                ->state(fn (DeliveryNote $record): HtmlString => UserPopup::list($record->assignees))
                ->html()
                ->disabledClick()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    User::query()->select('users.name')->join('delivery_note_user', 'delivery_note_user.user_id', '=', 'users.id')
                        ->whereColumn('delivery_note_user.delivery_note_id', 'delivery_notes.id')->orderBy('users.name')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }
}
