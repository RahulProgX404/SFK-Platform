<?php

declare(strict_types=1);

namespace Modules\Production\Application\DailyReports;

use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Models\Order;
use Modules\Production\Models\DailyReport;

/**
 * 製造済数量 / 社内原価 / 製造売上 of a job = the sums of 出来上 / 社内原価 / 製造売上 of its daily reports (legacy
 * Customine on save; recalculated from all reports, also for the old job of a changed report and on deletes, which the
 * legacy flow missed). The job then recalculates 製造残数量 and its actual cost (JobCosting).
 */
final class JobProduction
{
    public function refresh(?int $orderId): void
    {
        $order = $orderId === null ? null : Order::query()->find($orderId);
        if ($order === null) {
            return;
        }

        $rows = DailyReport::query()->where('order_id', $order->id);
        $order->forceFill([
            'manufactured_quantity' => (string) $rows->clone()->sum('produced'),
            'internal_cost' => (int) $rows->clone()->sum('internal_cost'),
            'manufacturing_sales' => (int) $rows->clone()->sum('manufacturing_sales'),
        ]);
        $order->refreshTotals();
    }

    /**
     * ステータスの更新 「する」: the job takes ステータス更新値 (BR-PR-06) — when the report was added with it or it changed, so a
     * later edit of an old report does not set the job back. Only the statuses of 04 are offered (the legacy 発送作業中 /
     * 印刷完了 did not exist there).
     */
    public function applyStatus(DailyReport $report): void
    {
        if (! $report->update_status || $report->order_id === null || ! in_array($report->new_status, OrderChoices::STATUSES, true)) {
            return;
        }
        if (! $report->wasRecentlyCreated && ! $report->wasChanged(['update_status', 'new_status', 'order_id'])) {
            return;
        }

        Order::query()->whereKey($report->order_id)->first()?->forceFill(['status' => $report->new_status])->saveQuietly();
    }
}
