<?php

declare(strict_types=1);

namespace Modules\Sales\Filament;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\Invoices\InvoiceResource;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;
use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Receipt;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * The tabs of a 得意先 on its 01. 取引先マスタ record (design_ref/customer-*.png): 売上, 納品書, 請求書 (with its
 * statistics), 入金 and 売掛明細 (the months of 23. 売掛管理), and the cards 売上合計 / 入金合計 / 売掛残高 — each read
 * through its app's rights (onlyVisible), newest first, at most LIMIT rows.
 */
final class PartnerTabs
{
    public const LIMIT = 500;

    public static function register(RecordTabs $tabs, string $page): void
    {
        $customer = fn (Model $partner): bool => $partner instanceof Partner && $partner->is_customer;

        $tabs->tab($page, 'sales', '売上', 'heroicon-o-shopping-cart', 30,
            fn (Model $partner): bool => $customer($partner) && SaleResource::canViewAny(),
            fn (Model $partner): array => self::sales($partner));
        $tabs->tab($page, 'delivery_notes', '納品書', 'heroicon-o-truck', 40,
            fn (Model $partner): bool => $customer($partner) && DeliveryNoteResource::canViewAny(),
            fn (Model $partner): array => self::deliveryNotes($partner));
        $tabs->tab($page, 'invoices', '請求書', 'heroicon-o-document-text', 50,
            fn (Model $partner): bool => $customer($partner) && InvoiceResource::canViewAny(),
            fn (Model $partner): array => self::invoices($partner));
        $tabs->tab($page, 'receipts', '入金', 'heroicon-o-banknotes', 60,
            fn (Model $partner): bool => $customer($partner) && ReceiptResource::canViewAny(),
            fn (Model $partner): array => self::receipts($partner));
        $tabs->tab($page, 'statement', '売掛明細', 'heroicon-o-clipboard-document-list', 70,
            fn (Model $partner): bool => $customer($partner) && ReceivableResource::canViewAny(),
            fn (Model $partner): array => self::statement($partner));

        $tabs->cards($page, 20, fn (Model $partner): array => $customer($partner) ? self::cards($partner) : []);
    }

    /** @return list<array<string, string>> 売上合計, 入金合計, 売掛残高 (latest month of 23. 売掛管理) */
    private static function cards(Model $partner): array
    {
        $cards = [];
        if (SaleResource::canViewAny()) {
            $cards[] = SummaryCard::make('売上合計（税抜）', (int) SaleResource::onlyVisible(Sale::query())->where('customer_id', $partner->getKey())->sum('amount'), icon: 'heroicon-o-shopping-cart');
        }
        if (ReceiptResource::canViewAny()) {
            $cards[] = SummaryCard::make('入金合計', (int) ReceiptResource::onlyVisible(Receipt::query())->where('customer_id', $partner->getKey())->sum('settled_amount'), tone: 'green', icon: 'heroicon-o-banknotes');
        }
        if (ReceivableResource::canViewAny()) {
            $latest = ReceivableResource::onlyVisible(ReceivableMonth::query())->ofBasis(ReceivableResource::basis())
                ->where('customer_id', $partner->getKey())->orderByDesc('year_month')->first();
            $cards[] = SummaryCard::make('売掛残高', $latest->closing_balance ?? 0, tone: 'red', icon: 'heroicon-o-scale');
        }

        return $cards;
    }

    /** @return array<string, mixed> */
    private static function sales(Model $partner): array
    {
        $sales = SaleResource::onlyVisible(Sale::query())->where('customer_id', $partner->getKey())
            ->orderByDesc('sold_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['number' => '売上No', 'date' => '売上日', 'product' => '品名', 'quantity' => '売上数量', 'amount' => '売上金額'],
            'numeric' => ['quantity', 'amount'],
            'rows' => $sales->map(fn (Sale $sale): array => [
                'number' => (string) $sale->sales_number,
                'date' => $sale->sold_on->format('Y-m-d'),
                'product' => (string) $sale->getAttribute('product_name'),
                'quantity' => SalesFormat::number($sale->getAttribute('quantity')),
                'amount' => AppFormat::yen($sale->amount),
            ])->all(),
            'urls' => $sales->map(fn (Sale $sale): string => SaleResource::getUrl('view', ['record' => $sale]))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function deliveryNotes(Model $partner): array
    {
        $notes = DeliveryNoteResource::onlyVisible(DeliveryNote::query())->where('customer_id', $partner->getKey())
            ->orderByDesc('sold_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['number' => '納品書番号', 'date' => '売上日', 'subtotal' => '税抜金額', 'tax' => '消費税', 'send' => '送付'],
            'numeric' => ['subtotal', 'tax'],
            'rows' => $notes->map(fn (DeliveryNote $note): array => [
                'number' => $note->number,
                'date' => (string) $note->sold_on?->format('Y-m-d'),
                'subtotal' => AppFormat::yen($note->subtotal),
                'tax' => AppFormat::yen($note->tax_total),
                'send' => $note->send_status,
            ])->all(),
            'urls' => $notes->map(fn (DeliveryNote $note): string => DeliveryNoteResource::getUrl('view', ['record' => $note]))->all(),
            'tones' => ['send' => ['済' => 'green', '未' => 'amber', '未送済' => 'blue']],
        ];
    }

    /** @return array<string, mixed> 請求書 with its statistics (件数, 御請求額, 今回御買上額, 消費税, ご入金額) */
    private static function invoices(Model $partner): array
    {
        $query = InvoiceResource::onlyVisible(Invoice::query())->where('customer_id', $partner->getKey());
        $totals = $query->clone()->toBase()->reorder()
            ->selectRaw('count(*) as aggregate_count, coalesce(sum(billed_total), 0) as billed, coalesce(sum(subtotal), 0) as subtotal, '
                .'coalesce(sum(tax_total), 0) as tax, coalesce(sum(received_amount), 0) as received')
            ->first();
        $invoices = $query->orderByDesc('invoiced_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'cards' => [
                SummaryCard::make('請求書', (int) ($totals->aggregate_count ?? 0), 'count'),
                SummaryCard::make('御請求額合計', (int) ($totals->billed ?? 0)),
                SummaryCard::make('今回御買上額合計（税抜）', (int) ($totals->subtotal ?? 0)),
                SummaryCard::make('消費税合計', (int) ($totals->tax ?? 0)),
                SummaryCard::make('ご入金額合計', (int) ($totals->received ?? 0)),
            ],
            'columns' => ['number' => '請求書番号', 'date' => '請求日付', 'kind' => '請求区分', 'subtotal' => '今回御買上額(税抜)', 'billed' => '御請求額', 'send' => '送付'],
            'numeric' => ['subtotal', 'billed'],
            'rows' => $invoices->map(fn (Invoice $invoice): array => [
                'number' => $invoice->number,
                'date' => (string) $invoice->invoiced_on?->format('Y-m-d'),
                'kind' => $invoice->kind,
                'subtotal' => AppFormat::yen($invoice->subtotal),
                'billed' => AppFormat::yen($invoice->billed_total),
                'send' => $invoice->send_status,
            ])->all(),
            'urls' => $invoices->map(fn (Invoice $invoice): string => InvoiceResource::getUrl('view', ['record' => $invoice]))->all(),
            'tones' => ['send' => ['済' => 'green', '未' => 'amber', '未送済' => 'blue'], 'kind' => ['通常請求' => 'slate', '返金処理' => 'red']],
        ];
    }

    /** @return array<string, mixed> */
    private static function receipts(Model $partner): array
    {
        $receipts = ReceiptResource::onlyVisible(Receipt::query())->where('customer_id', $partner->getKey())
            ->orderByDesc('billed_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['date' => '請求日付', 'billed' => '御請求額', 'settled' => '入金額', 'rest' => '差額'],
            'numeric' => ['billed', 'settled', 'rest'],
            'rows' => $receipts->map(fn (Receipt $receipt): array => [
                'date' => (string) $receipt->billed_on?->format('Y-m-d'),
                'billed' => AppFormat::yen($receipt->billed_amount),
                'settled' => AppFormat::yen($receipt->settled_amount),
                'rest' => AppFormat::yen($receipt->billed_amount - $receipt->settled_amount),
            ])->all(),
            'urls' => $receipts->map(fn (Receipt $receipt): string => ReceiptResource::getUrl('view', ['record' => $receipt]))->all(),
        ];
    }

    /** @return array<string, mixed> 売掛明細: the months of 23. 売掛管理 (calendar months) of the customer */
    private static function statement(Model $partner): array
    {
        $months = ReceivableResource::onlyVisible(ReceivableMonth::query())->ofBasis(ReceivableResource::basis())
            ->where('customer_id', $partner->getKey())->orderByDesc('year_month')->limit(self::LIMIT)->get();
        $latest = $months->first();

        return [
            'cards' => [
                SummaryCard::make('売上合計', (int) $months->sum('month_total')),
                SummaryCard::make('入金合計', (int) $months->sum('received')),
                SummaryCard::make('売掛残高', $latest->closing_balance ?? 0),
            ],
            'columns' => ['month' => '年月', 'previous' => '前月残高', 'received' => '入金', 'sales' => '売上（税込）', 'balance' => '残高'],
            'numeric' => ['previous', 'received', 'sales', 'balance'],
            'rows' => $months->map(fn (ReceivableMonth $month): array => [
                'month' => $month->year_month,
                'previous' => AppFormat::yen($month->previous_balance),
                'received' => AppFormat::yen($month->received),
                'sales' => AppFormat::yen($month->month_total),
                'balance' => AppFormat::yen($month->closing_balance),
            ])->all(),
            'urls' => $months->map(fn (ReceivableMonth $month): string => ReceivableResource::getUrl('view', ['record' => $month]))->all(),
        ];
    }
}
