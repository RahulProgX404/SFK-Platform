<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use Modules\Analytics\Filament\Dashboard\DashboardCard;
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

        return [
            'cash' => $service->cashFlow($period),
            // 個人: no 出金 (a 支払 has no 担当者) — the user's sales still to be paid instead
            'waiting' => $service->isPersonal() ? $service->overview($period, $this->today())['statuses'] : null,
            'receiptsUrl' => self::appUrl(ReceiptResource::appCode()),
        ];
    }
}
