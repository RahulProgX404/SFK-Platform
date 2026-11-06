<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Domain\Period;
use Modules\Analytics\Filament\Widgets\PersonalOverviewCard;

/**
 * 個人の概要 on the page of a user (ユーザー管理, 02. 従業員マスタ; bug.md 2026-11-06), above the record: that person's
 * figures for 今月 / 先月 / 過去30日間 / 今年度. Added to those pages through Core's UserDetailWidgets, only for viewers who
 * may see the person's rates (themselves and administrators — EmployeeDataAccess::canViewRates, checked again here).
 * Not in Filament/Widgets, so it is not a card of the dashboard.
 */
final class UserOverviewCard extends PersonalOverviewCard
{
    /** the user of the page (Filament gives the record of a resource page to its widgets) */
    public ?Model $record = null;

    /** a key of Period::PRESETS */
    public string $range = 'month';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::actor() !== null;
    }

    public function updatedRange(string $value): void
    {
        if (! array_key_exists($value, Period::PRESETS)) {
            $this->range = 'month';
        }
    }

    protected function person(): ?User
    {
        return $this->record instanceof User ? $this->record : null;
    }

    protected function overviewPeriod(): Period
    {
        return Period::preset($this->range, $this->today());
    }

    protected function ranges(): array
    {
        return Period::PRESETS;
    }
}
