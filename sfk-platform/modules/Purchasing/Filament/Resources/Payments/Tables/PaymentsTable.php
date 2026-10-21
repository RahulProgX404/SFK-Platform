<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Filament\Resources\Payments\PaymentLayout;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;

/**
 * Record list of 07. 支払入力 with the legacy view 支払入力一覧 (SYSTEM_BRAIN APP_07 views.md: 支払日, 支払入力ID, 支払内容詳細,
 * 支払金額合計, newest first) — 支払入力一覧(テーブル表示) had the same columns and is not repeated; the search box replaces
 * 支払一覧(検索特化). 支払内容詳細 lists 仕入先 / 金種名 / 支払額 of each row. インライン編集 changes 支払日 (the rows follow).
 */
final class PaymentsTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['paid_on', 'id', 'lines', 'total'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'paid_on', 'total', 'lines', 'created_at'] as $name) {
            $columns[$name] = PaymentLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '支払入力一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lines.supplier'))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(PaymentResource::class))
            ->emptyStateIcon(Heroicon::OutlinedCreditCard)
            ->emptyStateHeading('支払がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_07 fields.md); the row fields
     * match payments with any such row.
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => PaymentLayout::label($name);

        return [
            FilterField::date('paid_on', $label('paid_on')),
            FilterField::number('total', $label('total')),
            FilterField::choice('supplier_id', $label('supplier_id'), fn (): array => Partner::query()->where('is_supplier', true)->orderByDesc('id')
                ->pluck('name', 'id')->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])->all())
                ->relationship('lines', 'supplier_id'),
            FilterField::choice('method', $label('method'), PurchaseChoices::options(PurchaseChoices::PAYMENT_METHODS))->relationship('lines', 'method'),
            FilterField::number('amount', $label('amount'))->relationship('lines', 'amount'),
            FilterField::text('notes', $label('notes'))->relationship('lines', 'notes'),
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
        return [InlineField::date('paid_on')->required()];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(PaymentLayout::label($name))->placeholder('');

        return match ($name) {
            'paid_on' => $column->date('Y-m-d'),
            'created_at' => $column->dateTime('Y-m-d H:i'),
            'total' => $column->alignEnd()->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            'lines' => $column
                ->state(fn (Payment $record): array => $record->lines
                    ->map(fn (PaymentLine $line): string => trim(($line->supplier->name ?? '').' '.$line->method.' '.AppFormat::yen($line->amount)))
                    ->all())
                ->listWithLineBreaks()
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('lines', fn (Builder $q): Builder => $q
                    ->whereLike('notes', "%{$search}%")
                    ->orWhereHas('supplier', fn (Builder $s): Builder => $s->whereLike('name', "%{$search}%"))))
                // by the 仕入先 of the first row
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    Partner::query()->select('partners.name')->join('payment_lines', 'payment_lines.supplier_id', '=', 'partners.id')
                        ->whereColumn('payment_lines.payment_id', 'payments.id')->orderBy('payment_lines.sort')->orderBy('payment_lines.id')->limit(1),
                    $direction,
                )),
            default => $column,
        };
    }
}
