<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Calendar;

use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Modules\Core\Filament\Calendar\Concerns\ShowsCompanyCalendar;

/**
 * 会社カレンダー for everyone (user 2026-11-06): the calendar of 共通管理 read-only, in the sidebar of the dashboard
 * (白石封筒, ErpPanelProvider) above the spaces, in the look of the app screens (breadcrumb home › 会社カレンダー).
 * Administrators mark the days in 共通管理 → 会社カレンダー.
 */
final class CompanyCalendarPage extends Page
{
    use ShowsCompanyCalendar;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = '会社カレンダー';

    protected static ?string $title = '会社カレンダー';

    protected static ?string $slug = 'calendar';

    /** above the apps (they are grouped by space; this item has no group) */
    protected static ?int $navigationSort = -100;

    protected string $view = 'core::filament.company-calendar-page';

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User;
    }

    public function getHeader(): View
    {
        return view('core::filament.company-calendar-header', [
            'title' => (string) self::$title,
            'homeUrl' => Filament::getCurrentOrDefaultPanel()->getUrl(),
        ]);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-calendar-page'];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return $this->calendarViewData(editable: false);
    }
}
