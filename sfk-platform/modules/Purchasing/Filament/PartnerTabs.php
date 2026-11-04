<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\Payment;
use Modules\Purchasing\Models\PaymentLine;
use Modules\Purchasing\Models\Purchase;

/**
 * The tabs of a 仕入先 on its 01. 取引先マスタ record: 仕入 (05), 支払 (07, the lines paid to it) and 買掛明細 (the
 * months of 23. 買掛管理), and the cards 仕入合計 / 買掛残高 — read through the apps' rights (onlyVisible), newest
 * first, at most LIMIT rows.
 */
final class PartnerTabs
{
    public const LIMIT = 500;

    public static function register(RecordTabs $tabs, string $page): void
    {
        $supplier = fn (Model $partner): bool => $partner instanceof Partner && $partner->is_supplier;

        $tabs->tab($page, 'purchases', '仕入', 'heroicon-o-inbox-arrow-down', 80,
            fn (Model $partner): bool => $supplier($partner) && PurchaseResource::canViewAny(),
            fn (Model $partner): array => self::purchases($partner));
        $tabs->tab($page, 'payments', '支払', 'heroicon-o-credit-card', 90,
            fn (Model $partner): bool => $supplier($partner) && PaymentResource::canViewAny(),
            fn (Model $partner): array => self::payments($partner));
        $tabs->tab($page, 'payables', '買掛明細', 'heroicon-o-clipboard-document-list', 100,
            fn (Model $partner): bool => $supplier($partner) && PayableResource::canViewAny(),
            fn (Model $partner): array => self::payables($partner));

        $tabs->cards($page, 30, fn (Model $partner): array => $supplier($partner) ? self::cards($partner) : []);
    }

    /** @return list<array<string, string>> */
    private static function cards(Model $partner): array
    {
        $cards = [];
        if (PurchaseResource::canViewAny()) {
            $cards[] = SummaryCard::make('仕入合計', (int) PurchaseResource::onlyVisible(Purchase::query())->where('supplier_id', $partner->getKey())->sum('total'), tone: 'amber', icon: 'heroicon-o-inbox-arrow-down');
        }
        if (PayableResource::canViewAny()) {
            $latest = PayableResource::onlyVisible(PayableMonth::query())->where('supplier_id', $partner->getKey())->orderByDesc('year_month')->first();
            $cards[] = SummaryCard::make('買掛残高', $latest->closing_balance ?? 0, tone: 'purple', icon: 'heroicon-o-scale');
        }

        return $cards;
    }

    /** @return array<string, mixed> */
    private static function purchases(Model $partner): array
    {
        $purchases = PurchaseResource::onlyVisible(Purchase::query())->where('supplier_id', $partner->getKey())
            ->orderByDesc('purchased_on')->orderByDesc('id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['number' => '仕入No', 'date' => '仕入日', 'product' => '品名', 'amount' => '金額', 'total' => '合計'],
            'numeric' => ['amount', 'total'],
            'rows' => $purchases->map(fn (Purchase $purchase): array => [
                'number' => (string) $purchase->purchase_number,
                'date' => $purchase->purchased_on->format('Y-m-d'),
                'product' => (string) $purchase->product_name,
                'amount' => AppFormat::yen($purchase->amount),
                'total' => AppFormat::yen($purchase->total),
            ])->all(),
            'urls' => $purchases->map(fn (Purchase $purchase): string => PurchaseResource::getUrl('view', ['record' => $purchase]))->all(),
        ];
    }

    /** @return array<string, mixed> the 支払 lines to the supplier, with the date of their 支払 */
    private static function payments(Model $partner): array
    {
        $lines = PaymentLine::query()->with('payment')->where('supplier_id', $partner->getKey())
            ->whereIn('payment_id', PaymentResource::onlyVisible(Payment::query())->select('id'))
            ->orderByDesc('payment_id')->limit(self::LIMIT)->get();

        return [
            'columns' => ['date' => '支払日', 'method' => '支払方法', 'amount' => '金額', 'notes' => '備考'],
            'numeric' => ['amount'],
            'rows' => $lines->map(fn (PaymentLine $line): array => [
                'date' => $line->payment->paid_on->format('Y-m-d'),
                'method' => $line->method,
                'amount' => AppFormat::yen($line->amount),
                'notes' => (string) $line->notes,
            ])->all(),
            'urls' => $lines->map(fn (PaymentLine $line): string => PaymentResource::getUrl('view', ['record' => $line->payment_id]))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function payables(Model $partner): array
    {
        $months = PayableResource::onlyVisible(PayableMonth::query())->where('supplier_id', $partner->getKey())
            ->orderByDesc('year_month')->limit(self::LIMIT)->get();

        return [
            'columns' => ['month' => '年月', 'previous' => '前月残高', 'paid' => '支払', 'purchases' => '仕入（税込）', 'balance' => '残高'],
            'numeric' => ['previous', 'paid', 'purchases', 'balance'],
            'rows' => $months->map(fn (PayableMonth $month): array => [
                'month' => $month->year_month,
                'previous' => AppFormat::yen($month->previous_balance),
                'paid' => AppFormat::yen($month->paid),
                'purchases' => AppFormat::yen($month->month_total),
                'balance' => AppFormat::yen($month->closing_balance),
            ])->all(),
            'urls' => $months->map(fn (PayableMonth $month): string => PayableResource::getUrl('view', ['record' => $month]))->all(),
        ];
    }
}
