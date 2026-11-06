<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\BagSchedules\Pages;

use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Production\Filament\Resources\BagSchedules\BagScheduleResource;
use Modules\Production\Filament\Resources\BagSchedules\Tables\BagSchedulesTable;

/**
 * List of 12. 製袋予定管理. Its first view, 製袋予定表 (カスタマイズ形式), is the board of production-schedule.js
 * (Board\BagScheduleBoard): the lines × days with 「1日」「7日」「30日」, adding, editing, moving and copying in place. The
 * Gantt views of the legacy app (stored as gantt-day … gantt-year) show the board too.
 */
final class ListBagSchedules extends AppListRecords
{
    protected static string $resource = BagScheduleResource::class;

    public function listColumns(): array
    {
        return BagSchedulesTable::columns();
    }

    /** 製袋予定表 and the legacy 帳票出力用(テスト) (SYSTEM_BRAIN APP_12 views.md). */
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
        $views = [BagSchedulesTable::VIEW_BOARD => 'production::filament.bag-schedule-board-view'];
        foreach (['gantt-day', 'gantt-week', 'gantt-month', 'gantt-year'] as $legacy) {
            $views[$legacy] = 'production::filament.bag-schedule-board-view';
        }

        return $views;
    }
}
