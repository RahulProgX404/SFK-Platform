<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Analytics\Filament\Dashboard\DashboardTips;
use Modules\Core\Filament\Support\Tip;
use Modules\Purchasing\Filament\Resources\Payments\PaymentResource;
use Modules\Sales\Filament\Resources\Receipts\ReceiptResource;

/**
 * 入出金 (row 1, end; design_ref cash-flow.png, Cash Flow): 入金 of 21. 売入金管理 (入金日), 出金 of 07. 支払入力 (支払日) and
 * the difference, for the period.
 */
final class CashFlowCard extends DashboardCard
{
    protected static ?int $sort = 3;

    protected string $view = 'analytics::widgets.cash-flow';

    public static function canView(): bool
    {
        return (self::actor()?->seesFinancialAnalysis() ?? false) && (ReceiptResource::canViewAny() || PaymentResource::canViewAny());
    }

    /** Not in the 個人 layout (bug.md 2026-11-09). */
    protected static function shownInLayout(bool $personal): bool
    {
        return ! $personal;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = $this->salesData();
        $period = $this->period();
        $cash = $service->cashFlow($period);
        // 個人: no 出金 (a 支払 has no 担当者) — the user's sales still to be paid instead
        $waiting = $service->isPersonal() ? $service->overview($period, $this->today())['statuses'] : null;
        $status = fn (string $key): Tip => DashboardTips::status($key, SalesDashboard::STATUSES[$key]);

        return [
            'cash' => $cash,
            'waiting' => $waiting,
            // the tooltips (DashboardTips — from the values above, nothing more is read)
            'tips' => [
                '入金' => DashboardTips::cash('inflow', $service->isPersonal()),
                '出金' => DashboardTips::cash('outflow', false),
                '差引' => DashboardTips::cash('net', false),
                '入金待ち' => $waiting !== null ? $status('pending') : null,
                '期限超過' => $waiting !== null ? $status('overdue') : null,
            ],
            'receiptsUrl' => self::appUrl(ReceiptResource::appCode()),
        ];
    }
}
