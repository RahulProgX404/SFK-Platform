<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Calendar\CalendarDayType;

/**
 * A marked day of the 会社カレンダー (祝日 / 会社休日 / 半日出勤); a day without a row is a working day, a Sunday
 * without a row is closed. Maintained in 共通管理 → 会社カレンダー through Application\Calendar\CompanyCalendar.
 *
 * @property int $id
 * @property CarbonImmutable $day
 * @property CalendarDayType $type
 */
final class CompanyCalendarDay extends Model
{
    protected $fillable = ['day', 'type'];

    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'type' => CalendarDayType::class,
        ];
    }
}
