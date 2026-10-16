<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Views;

/** レコード一覧の表示形式 of a list view. (The legacy カスタマイズ format with free HTML is not offered.) */
enum ListViewType: string
{
    /** 表形式: rows with the chosen columns */
    case Table = 'table';
    /** カレンダー形式: the records on a month calendar by a date field, shown by a title field */
    case Calendar = 'calendar';

    public function label(): string
    {
        return match ($this) {
            self::Table => '表形式',
            self::Calendar => 'カレンダー形式',
        };
    }
}
