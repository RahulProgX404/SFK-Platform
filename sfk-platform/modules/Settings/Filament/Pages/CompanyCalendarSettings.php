<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Renderless;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Core\Domain\Calendar\SeedCalendars;
use Modules\Core\Filament\Calendar\Concerns\ShowsCompanyCalendar;
use Modules\Settings\Filament\NavigationGroup;
use Throwable;
use UnitEnum;

/**
 * 会社カレンダー (editor, administrators): a click on a day cycles 平日 → 祝日 → 会社休日 → 半日出勤 → 平日 and is saved at
 * once (the screen changes first and goes back with a red ring when saving fails). Sundays are 祝日 without being
 * marked. 初期データを読み込む replaces the year with the printed calendar (SeedCalendars). Everyone else sees the
 * calendar read-only on the dashboards (Core\Filament\Calendar\CompanyCalendarPage).
 */
final class CompanyCalendarSettings extends Page
{
    use ShowsCompanyCalendar;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Company->value;

    protected static ?string $navigationLabel = '会社カレンダー';

    protected static ?string $title = '会社カレンダー';

    protected static ?string $slug = 'calendar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'settings::pages.company-calendar';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator();
    }

    /** A day clicked: kept as the given type ('' = 平日). Returns the message to show when it was not saved. */
    #[Renderless]
    public function setDay(string $date, string $type = ''): ?string
    {
        abort_unless(self::canAccess(), 403);

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $date) : null;
        if (! $day instanceof CarbonImmutable || $day->toDateString() !== $date || $day->year < CompanyCalendar::FIRST_YEAR || $day->year > CompanyCalendar::LAST_YEAR) {
            return '日付の形式が正しくありません。';
        }
        $dayType = $type === '' ? null : CalendarDayType::tryFrom($type);
        if ($type !== '' && $dayType === null) {
            return '無効な種別です。';
        }

        try {
            app(CompanyCalendar::class)->setDay($day, $dayType);
        } catch (Throwable $exception) {
            report($exception);

            return '更新できませんでした。';
        }

        return null;
    }

    public function importSeedAction(): Action
    {
        return Action::make('importSeed')
            ->label('初期データを読み込む')
            ->color('gray')
            ->visible(fn (): bool => SeedCalendars::has($this->selectedYear()))
            ->requiresConfirmation()
            ->modalIcon(null)
            ->modalHeading('確認')
            ->modalDescription(fn (): string => "{$this->selectedYear()}年の設定を初期データで上書きします。よろしいですか？")
            ->modalSubmitActionLabel('上書きする')
            ->action(function (): void {
                abort_unless(self::canAccess(), 403);
                app(CompanyCalendar::class)->importSeed($this->selectedYear());
                $this->version++;
                Notification::make()->success()->title('初期データを読み込みました')->send();
            });
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [$this->importSeedAction()];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return $this->calendarViewData(editable: true);
    }
}
