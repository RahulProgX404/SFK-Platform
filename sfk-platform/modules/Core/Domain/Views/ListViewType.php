<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Views;

/**
 * レコード一覧の表示形式 of a list view. カスタマイズ形式 is an app-defined screen (the legacy CUSTOM views, e.g. the Gantt charts of
 * 12. 製袋予定管理) instead of free HTML: an app offers it in code (AppListRecords::customListViews()), it is not created in
 * the 一覧 editor.
 */
enum ListViewType: string
{
    /** 表形式: rows with the chosen columns */
    case Table = 'table';
    /** カレンダー形式: the records on a month calendar by a date field, shown by a title field */
    case Calendar = 'calendar';
    /** カスタマイズ形式: a screen of the app (AppListRecords::customListViews()) over the records of the view */
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Table => '表形式',
            self::Calendar => 'カレンダー形式',
            self::Custom => 'カスタマイズ形式',
        };
    }
}
