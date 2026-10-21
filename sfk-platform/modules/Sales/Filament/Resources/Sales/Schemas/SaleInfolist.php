<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Schemas;

use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Records\RecordTable;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Sales\SaleLayout;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\SalesFormat;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleAllocation;

/**
 * Record page of 06. 売上入力, laid out like the legacy record (SaleLayout), incl. 作成者 / 更新者; the customer's and the
 * job's values come from the masters, 同注番の売上履歴 lists the job's other sales newest first (legacy related list:
 * 売上日, 売上単価, 売上数量, 売上金額; empty while there are none). 納品書番号 / 請求書番号 link to the documents.
 */
final class SaleInfolist
{
    private const YEN = ['amount', 'estimated_cost', 'estimated_expense', 'tax'];

    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(SaleLayout::components(self::entry(...), withFooter: true, withDeveloperFields: $superAdmin));
    }

    private static function entry(string $name): Component
    {
        if ($name === 'allocations') {
            return self::allocations();
        }
        if ($name === 'same_order_sales') {
            return View::make('core::filament.related-table')
                ->viewData(fn (Sale $record): array => self::sameOrderSales($record))
                ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false));
        }

        $entry = TextEntry::make($name)->label(SaleLayout::label($name))->placeholder('—');
        $customer = fn (Sale $record, string $attribute): ?string => $record->customer->getAttribute($attribute);

        return match (true) {
            in_array($name, self::YEN, true) => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::yen($state)),
            $name === 'quantity' => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::number($state)),
            $name === 'unit_price' => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::price($state)),
            $name === 'amount_with_tax' => $entry->state(fn (Sale $record): string => SalesFormat::yen($record->amountWithTax())),
            in_array($name, ['sold_on', 'billing_date', 'delivery_due_on'], true) => $entry->date('Y-m-d'),
            $name === 'customer_id' => $entry->state(fn (Sale $record): string => $record->customer->name),
            $name === 'other_address' => $entry->state(fn (Sale $record): ?string => $record->other_address ? 'する' : null),
            $name === 'parent_postal_code' => $entry->state(fn (Sale $record): ?string => $customer($record, 'parent_postal_code')),
            $name === 'parent_address' => $entry->state(fn (Sale $record): ?string => $customer($record, 'parent_address')),
            $name === 'customer_postal_code' => $entry->state(fn (Sale $record): ?string => $customer($record, 'postal_code')),
            $name === 'customer_address' => $entry->state(fn (Sale $record): ?string => $customer($record, 'address')),
            $name === 'order_id' => $entry->state(fn (Sale $record): ?string => $record->order?->order_number),
            $name === 'order_customer' => $entry->state(fn (Sale $record): ?string => $record->order?->customer?->name),
            $name === 'order_quantity' => $entry->state(fn (Sale $record): string => SalesFormat::number($record->order?->quantity)),
            $name === 'order_sold_quantity' => $entry->state(fn (Sale $record): string => SalesFormat::number($record->order?->getAttribute('sold_quantity'))),
            $name === 'order_remaining' => $entry->state(fn (Sale $record): string => SalesFormat::number($record->order?->getAttribute('sales_remaining'))),
            $name === 'order_sales_amount' => $entry->state(fn (Sale $record): string => SalesFormat::yen($record->order?->sales_amount)),
            $name === 'order_sold_amount' => $entry->state(fn (Sale $record): string => SalesFormat::yen($record->order?->getAttribute('sold_amount'))),
            $name === 'is_fsc' => $entry->state(fn (Sale $record): ?string => $record->order?->is_fsc === true ? SaleChoices::FSC_CODE : null),
            $name === 'needs', $name === 'handover' => $entry->state(fn (Sale $record): ?string => implode('、', (array) $record->getAttribute($name)) ?: null),
            $name === 'delivered' => $entry->state(fn (Sale $record): ?string => $record->delivery_note_id !== null ? '済' : null),
            $name === 'invoiced' => $entry->state(fn (Sale $record): ?string => $record->invoice_id !== null ? '済' : null),
            $name === 'delivery_note_id' => $entry
                ->state(fn (Sale $record): ?string => $record->deliveryNote?->number)
                ->url(fn (Sale $record): ?string => $record->deliveryNote !== null && DeliveryNoteResource::canView($record->deliveryNote)
                    ? DeliveryNoteResource::getUrl('view', ['record' => $record->deliveryNote]) : null),
            $name === 'invoice_id' => $entry
                ->state(fn (Sale $record): ?string => $record->invoice?->number)
                ->url(fn (Sale $record): ?string => $record->invoice !== null && InvoiceResource::canView($record->invoice)
                    ? InvoiceResource::getUrl('view', ['record' => $record->invoice]) : null),
            $name === 'locked' => $entry->state(fn (Sale $record): string => $record->locked ? '不可' : '可'),
            $name === 'postal_code_p' => $entry->state(fn (Sale $record): ?string => $record->deliveryAddress()['postal_code']),
            $name === 'address_p' => $entry->state(fn (Sale $record): ?string => $record->deliveryAddress()['address']),
            $name === 'customer_code' => $entry->state(fn (Sale $record): ?string => $record->customer->code),
            $name === 'effective_closing_day' => $entry->state(fn (Sale $record): ?string => $record->effectiveClosingDay()?->value),
            $name === 'billing_month' => $entry->formatStateUsing(fn (mixed $state): string => SalesFormat::month(is_string($state) ? $state : null)),
            $name === 'sales_month' => $entry->state(fn (Sale $record): string => $record->sold_on->format('Y年m月')),
            // user names open the ユーザー情報 popup
            $name === 'assignees' => $entry->state(fn (Sale $record): HtmlString => UserPopup::list($record->assignees))->html(),
            $name === 'creator', $name === 'updater' => $entry->state(fn (Sale $record): HtmlString => UserPopup::html($record->{$name}))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }

    private static function allocations(): RepeatableEntry
    {
        return RecordTable::entry(
            RepeatableEntry::make('allocations')->label(SaleLayout::label('allocations'))->placeholder(''),
            [[SaleLayout::label('allocation_user'), 344], [SaleLayout::label('allocation_ratio'), 89], [SaleLayout::label('allocation_amount'), 193]],
        )->schema([
            TextEntry::make('user_id')->state(fn (SaleAllocation $record): HtmlString => UserPopup::html($record->user))->html()->placeholder(''),
            TextEntry::make('ratio')->alignEnd()->formatStateUsing(fn (mixed $state): string => filled($state) ? SalesFormat::number($state).'%' : '')->placeholder(''),
            TextEntry::make('allocation_amount')->alignEnd()->placeholder('')
                ->state(fn (Model $record): string => $record instanceof SaleAllocation
                    ? SalesFormat::yen(SaleAllocation::share((int) $record->sale->amount, $record->ratio))
                    : ''),
        ]);
    }

    /** @return array{title: string, columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>} */
    private static function sameOrderSales(Sale $record): array
    {
        $sales = $record->order_id === null
            ? collect()
            : Sale::query()->where('order_id', $record->order_id)->whereKeyNot($record->id)->orderByDesc('id')->get();

        return [
            'title' => SaleLayout::label('same_order_sales'),
            'columns' => ['sold_on' => '売上日', 'unit_price' => '売上単価', 'quantity' => '売上数量', 'amount' => '売上金額'],
            'numeric' => ['unit_price', 'quantity', 'amount'],
            'rows' => $sales->map(fn (Sale $sale): array => [
                'sold_on' => SalesFormat::date($sale->sold_on),
                'unit_price' => SalesFormat::price($sale->unit_price),
                'quantity' => SalesFormat::number($sale->quantity),
                'amount' => SalesFormat::yen($sale->amount),
            ])->values()->all(),
            'urls' => $sales->map(fn (Sale $sale): ?string => SaleResource::canView($sale) ? SaleResource::getUrl('view', ['record' => $sale]) : null)->values()->all(),
        ];
    }
}
