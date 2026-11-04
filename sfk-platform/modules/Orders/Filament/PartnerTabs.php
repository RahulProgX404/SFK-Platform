<?php

declare(strict_types=1);

namespace Modules\Orders\Filament;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Orders\OrderFormat;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\Quotations\QuotationResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\Quotation;

/**
 * The tabs of a 得意先 on its 01. 取引先マスタ record (design_ref/customer-orders.png): 受注 and 見積, and the card
 * 受注件数 — read through the apps' rights (onlyVisible), newest first, at most LIMIT rows.
 */
final class PartnerTabs
{
    public const LIMIT = 500;

    public static function register(RecordTabs $tabs, string $page): void
    {
        $customer = fn (Model $partner): bool => $partner instanceof Partner && $partner->is_customer;

        $tabs->tab($page, 'orders', '受注', 'heroicon-o-clipboard-document-check', 10,
            fn (Model $partner): bool => $customer($partner) && OrderResource::canViewAny(),
            fn (Model $partner): array => self::orders($partner));
        $tabs->tab($page, 'quotations', '見積', 'heroicon-o-document-duplicate', 20,
            fn (Model $partner): bool => $customer($partner) && QuotationResource::canViewAny(),
            fn (Model $partner): array => self::quotations($partner));

        $tabs->cards($page, 10, fn (Model $partner): array => $customer($partner) && OrderResource::canViewAny()
            ? [SummaryCard::make('受注件数', OrderResource::onlyVisible(Order::query())->where('customer_id', $partner->getKey())->count(), 'count', 'purple', 'heroicon-o-clipboard-document-check')]
            : []);
    }

    /** @return array<string, mixed> */
    private static function orders(Model $partner): array
    {
        $orders = OrderResource::onlyVisible(Order::query())->where('customer_id', $partner->getKey())
            ->orderByDesc('order_date')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['number' => '注番', 'date' => '受注日', 'product' => '品名', 'quantity' => '数量', 'due' => '納期', 'amount' => '売上金額', 'status' => '状態'],
            'numeric' => ['quantity', 'amount'],
            'rows' => $orders->map(fn (Order $order): array => [
                'number' => $order->order_number,
                'date' => self::date($order->getAttribute('order_date')),
                'product' => (string) $order->product_name,
                'quantity' => OrderFormat::number($order->quantity),
                'due' => self::date($order->getAttribute('due_date')),
                'amount' => AppFormat::yen($order->sales_amount),
                'status' => (string) $order->status,
            ])->all(),
            'urls' => $orders->map(fn (Order $order): string => OrderResource::getUrl('view', ['record' => $order]))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function quotations(Model $partner): array
    {
        $quotations = QuotationResource::onlyVisible(Quotation::query())->where('customer_id', $partner->getKey())
            ->orderByDesc('quoted_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['number' => '見積番号', 'date' => '見積日', 'product' => '品名', 'total' => '合計'],
            'numeric' => ['total'],
            'rows' => $quotations->map(fn (Quotation $quotation): array => [
                'number' => (string) $quotation->quote_number,
                'date' => $quotation->quoted_on->format('Y-m-d'),
                'product' => (string) $quotation->product_name,
                'total' => AppFormat::yen($quotation->total),
            ])->all(),
            'urls' => $quotations->map(fn (Quotation $quotation): string => QuotationResource::getUrl('view', ['record' => $quotation]))->all(),
        ];
    }

    private static function date(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}
