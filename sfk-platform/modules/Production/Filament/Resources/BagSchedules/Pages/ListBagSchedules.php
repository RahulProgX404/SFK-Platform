<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\MasterData\Models\Machine;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Domain\Schedules\GanttChart;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Filament\Resources\BagSchedules\Tables\BagSchedulesTable;
use Modules\Production\Models\BagSchedule;

/**
 * List of 12. 製袋予定管理. The 日次 / 週次 / 月次 / 年次ガントチャート views (カスタマイズ形式) draw the bookings of the view
 * (its conditions and 絞り込む) by machine over the chosen period (‹ 今日 ›): one row per machine (by 機械コード), a bar
 * per booking coloured by 使用状況 — 使用前 orange, 使用中 red, 完了 grey, as in the legacy app — with 注番 / 品名 / 部数 /
 * 備考 (the legacy 表示用 fields); a bar opens its record. Not on phones in the legacy app; here the chart scrolls sideways.
 */
final class ListBagSchedules extends AppListRecords
{
    protected static string $resource = BagScheduleResource::class;

    /** Most bookings drawn on one chart. */
    private const GANTT_LIMIT = 2000;

    /** The day the chart is around (Y-m-d); blank = today. */
    #[Url(as: 'date')]
    public string $ganttDate = '';

    public function listColumns(): array
    {
        return BagSchedulesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_12 views.md). */
    public function defaultListViews(): array
    {
        return BagSchedulesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return BagSchedulesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return BagSchedulesTable::inlineFields();
    }

    public function customListViews(): array
    {
        $views = [];
        foreach (GanttChart::SCALES as $scale) {
            $views['gantt-'.$scale] = 'production::filament.bag-schedule-gantt';
        }

        return $views;
    }

    /** The scale of the current Gantt view. */
    public function ganttScale(): string
    {
        $scale = substr((string) $this->currentView()->customView, strlen('gantt-'));

        return in_array($scale, GanttChart::SCALES, true) ? $scale : GanttChart::WEEK;
    }

    public function ganttDay(): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $this->ganttDate);

        return $day !== false ? $day : CarbonImmutable::today();
    }

    public function shiftGantt(int $steps): void
    {
        $this->ganttDate = GanttChart::shift($this->ganttScale(), $this->ganttDay(), $steps)->format('Y-m-d');
    }

    public function showGanttToday(): void
    {
        $this->ganttDate = '';
    }

    /**
     * The chart of the current view and period.
     *
     * @return array{title: string, columns: list<array{label: string, start: DateTimeImmutable}>, rows: list<array{machine: string, lanes: int, bars: list<array{left: float, width: float, lane: int, item: array{url: string|null, color: string, status: string, label: string, title: string}}>}>, legend: array<string, string>}
     */
    public function ganttChart(): array
    {
        $scale = $this->ganttScale();
        [$start, $end] = GanttChart::period($scale, $this->ganttDay());

        $query = $this->getFilteredTableQuery();
        $bookings = $query instanceof Builder
            ? $query->clone()->reorder()->with(['machine', 'order'])
                ->where('bag_schedules.starts_at', '<', $end->format('Y-m-d H:i:s'))
                ->where('bag_schedules.ends_at', '>', $start->format('Y-m-d H:i:s'))
                ->orderBy('bag_schedules.starts_at')
                ->limit(self::GANTT_LIMIT)
                ->get()
                ->whereInstanceOf(BagSchedule::class)
            : collect();

        $machines = Machine::query()->orderBy('code')->orderBy('id')->get(['id', 'name', 'code']);
        $rows = [];
        foreach ([...$machines->all(), null] as $machine) {
            $mine = $bookings->filter(fn (BagSchedule $booking): bool => $booking->machine_id === $machine?->id)->values();
            if ($machine === null && $mine->isEmpty()) {
                continue;
            }

            $bars = GanttChart::bars($mine->map(fn (BagSchedule $booking): array => [
                'start' => $booking->starts_at?->toDateTimeImmutable(),
                'end' => $booking->ends_at?->toDateTimeImmutable(),
                'item' => $this->ganttItem($booking),
            ])->all(), $start, $end);

            $rows[] = [
                'machine' => $machine->name ?? '（機械なし）',
                'lanes' => $bars === [] ? 1 : max(array_column($bars, 'lane')) + 1,
                'bars' => $bars,
            ];
        }

        return [
            'title' => match ($scale) {
                GanttChart::DAY => $start->format('Y年n月j日'),
                GanttChart::WEEK => $start->format('Y年n月j日').' ～ '.$end->modify('-1 day')->format('n月j日'),
                GanttChart::MONTH => $start->format('Y年n月'),
                default => $start->format('Y年'),
            },
            'columns' => GanttChart::columns($scale, $this->ganttDay()),
            'rows' => $rows,
            'legend' => [
                '使用中' => ProductionChoices::USAGE_STATUSES['使用中'],
                '使用前' => ProductionChoices::USAGE_STATUSES['使用前'],
                '使用完了' => ProductionChoices::USAGE_STATUSES['完了'],
            ],
        ];
    }

    /** @return array{url: string|null, color: string, status: string, label: string, title: string} */
    private function ganttItem(BagSchedule $booking): array
    {
        $parts = array_filter([
            '注番：'.$booking->orderNumber(),
            '品名：'.$booking->productName(),
            filled($booking->copies) ? '部数：'.str_replace(',', '', AppFormat::number($booking->copies)) : null,
            filled($booking->notes) ? '備考：'.$booking->notes : null,
        ]);

        return [
            'url' => BagScheduleResource::canView($booking) ? BagScheduleResource::getUrl('view', ['record' => $booking]) : null,
            'color' => ProductionChoices::USAGE_STATUSES[(string) $booking->usage_status] ?? '#3498db',
            'status' => (string) $booking->usage_status,
            'label' => implode('　', $parts),
            'title' => trim(($booking->starts_at?->format('n/j H:i') ?? '').' ～ '.($booking->ends_at?->format('n/j H:i') ?? '')."\n".implode("\n", $parts)),
        ];
    }
}
