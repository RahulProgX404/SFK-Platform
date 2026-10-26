<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports;

use Modules\Core\Filament\Support\AppFormat;
use Modules\Orders\Models\Order;
use Modules\Production\Models\DailyReport;

/**
 * 日報一覧 on a 04. 受注入力 record (legacy related list of APP_04: 作業日, 作業者, 機械名, 開始時間, 終了時間, 型変作業時間,
 * 出来上, 作業人数, 難易度, 作業時間, 社内原価 — the job's daily reports, newest first, 10 rows).
 */
final class DailyReportRows
{
    /** @return array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>} */
    public static function ofOrder(Order $order): array
    {
        $reports = DailyReportResource::onlyVisible(DailyReport::query())->with(['worker', 'machine'])->where('order_id', $order->id)->orderByDesc('id')->limit(10)->get();
        $label = fn (string $name): string => DailyReportLayout::label($name);
        $time = fn (?string $value): string => $value === null ? '' : substr($value, 0, 5);

        return [
            'columns' => ['worked_on' => $label('worked_on'), 'user_id' => $label('user_id'), 'machine_id' => $label('machine_id'), 'started_at' => $label('started_at'),
                'ended_at' => $label('ended_at'), 'changeover_minutes' => $label('changeover_minutes'), 'produced' => $label('produced'), 'crew' => $label('crew'),
                'difficulty' => $label('difficulty'), 'work_hours' => $label('work_hours'), 'internal_cost' => $label('internal_cost')],
            'numeric' => ['changeover_minutes', 'produced', 'crew', 'work_hours', 'internal_cost'],
            'rows' => $reports->map(fn (DailyReport $report): array => [
                'worked_on' => AppFormat::date($report->worked_on),
                'user_id' => $report->worker->name,
                'machine_id' => (string) $report->machine?->name,
                'started_at' => $time($report->started_at),
                'ended_at' => $time($report->ended_at),
                'changeover_minutes' => AppFormat::number($report->changeover_minutes),
                'produced' => AppFormat::number($report->produced),
                'crew' => AppFormat::number($report->crew),
                'difficulty' => (string) $report->difficulty,
                'work_hours' => AppFormat::number($report->work_hours),
                'internal_cost' => AppFormat::yen($report->internal_cost),
            ])->values()->all(),
            'urls' => $reports->map(fn (DailyReport $report): ?string => DailyReportResource::canView($report)
                ? DailyReportResource::getUrl('view', ['record' => $report]) : null)->values()->all(),
        ];
    }
}
