<?php

/*
 * 会社カレンダー (company-calendar.js): 12 months of the year; a click cycles 平日 → 祝日 → 会社休日 → 半日出勤 → 平日
 * and is saved at once; Sundays are 祝日 without being marked; 初期データを読み込む replaces the year with the printed
 * calendar; administrators only.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Core\Domain\Calendar\SeedCalendars;
use Modules\Core\Models\CompanyCalendarDay;
use Modules\Settings\Filament\Pages\CompanyCalendarSettings;
use Modules\Settings\Filament\Widgets\SettingsMenuWidget;

beforeEach(function () {
    Filament::setCurrentPanel('settings');
    $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00'));
    $this->admin = User::factory()->admin()->create();
});

it('cycles a day 平日 → 祝日 → 会社休日 → 半日出勤 → 平日, with every Sunday 祝日 unmarked', function () {
    expect(CalendarDayType::after(null))->toBe(CalendarDayType::NationalHoliday)
        ->and(CalendarDayType::after(CalendarDayType::NationalHoliday))->toBe(CalendarDayType::CompanyHoliday)
        ->and(CalendarDayType::after(CalendarDayType::CompanyHoliday))->toBe(CalendarDayType::HalfDay)
        ->and(CalendarDayType::after(CalendarDayType::HalfDay))->toBeNull()
        ->and(CalendarDayType::effective(CarbonImmutable::parse('2026-11-08'), null))->toBe(CalendarDayType::NationalHoliday) // 日曜
        ->and(CalendarDayType::effective(CarbonImmutable::parse('2026-11-08'), CalendarDayType::HalfDay))->toBe(CalendarDayType::HalfDay)
        ->and(CalendarDayType::effective(CarbonImmutable::parse('2026-11-09'), null))->toBeNull()
        ->and(CalendarDayType::HalfDay->isClosed())->toBeFalse()
        ->and(CalendarDayType::CompanyHoliday->isClosed())->toBeTrue();

    $calendar = app(CompanyCalendar::class);
    $calendar->setDay(CarbonImmutable::parse('2026-11-07'), CalendarDayType::HalfDay);
    $calendar->setDay(CarbonImmutable::parse('2026-11-10'), CalendarDayType::CompanyHoliday);
    $calendar->setDay(CarbonImmutable::parse('2026-11-10'), CalendarDayType::NationalHoliday); // the same day again: one row
    $calendar->setDay(CarbonImmutable::parse('2026-11-11'), CalendarDayType::CompanyHoliday);
    $calendar->setDay(CarbonImmutable::parse('2026-11-11'), null);

    expect(CompanyCalendarDay::query()->count())->toBe(2)
        ->and($calendar->dayTypes(CarbonImmutable::parse('2026-11-06'), CarbonImmutable::parse('2026-11-11')))->toBe([
            '2026-11-07' => CalendarDayType::HalfDay,
            '2026-11-08' => CalendarDayType::NationalHoliday,
            '2026-11-10' => CalendarDayType::NationalHoliday,
        ])
        ->and($calendar->isClosed(CarbonImmutable::parse('2026-11-08')))->toBeTrue()
        ->and($calendar->isClosed(CarbonImmutable::parse('2026-11-07')))->toBeFalse()
        ->and($calendar->typeOf(CarbonImmutable::parse('2026-11-09')))->toBeNull()
        ->and($calendar->markedDays(2026))->toBe(['2026-11-07' => CalendarDayType::HalfDay, '2026-11-10' => CalendarDayType::NationalHoliday]);
});

it('shows the year as 12 months and saves a clicked day at once', function () {
    app(CompanyCalendar::class)->setDay(CarbonImmutable::parse('2026-08-13'), CalendarDayType::CompanyHoliday);
    $this->actingAs($this->admin);

    $this->get(CompanyCalendarSettings::getUrl())->assertOk()
        ->assertSeeInOrder(['会社カレンダー', '2026年', '1月', '日', '月', '火', '水', '木', '金', '土', '12月', '赤字', '＝祝日・日曜', '＝会社休日', '＝半日出勤（午前のみ）', '＝本日'])
        ->assertSee('data-date="2026-02-28"', false)
        ->assertSee('company_holiday', false);

    $page = Livewire::test(CompanyCalendarSettings::class)
        ->assertSet('year', '2026')
        ->assertViewHas('days', ['2026-08-13' => 'company_holiday'])
        ->assertViewHas('cycle', ['' => 'national_holiday', 'national_holiday' => 'company_holiday', 'company_holiday' => 'half_day', 'half_day' => '']);

    $page->call('setDay', '2026-08-13', 'half_day')->assertReturned(null);
    $page->call('setDay', '2026-08-14', 'national_holiday')->assertReturned(null);
    $page->call('setDay', '2026-08-14', '')->assertReturned(null);
    $page->call('setDay', '2026-02-30', 'half_day')->assertReturned('日付の形式が正しくありません。');
    $page->call('setDay', '2026-08-15', 'weekday')->assertReturned('無効な種別です。');

    expect(app(CompanyCalendar::class)->markedDays(2026))->toBe(['2026-08-13' => CalendarDayType::HalfDay]);
});

it('moves between the years, keeps ?year= within 2000–2100', function () {
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['year' => '2027'])->test(CompanyCalendarSettings::class)
        ->assertSet('year', '2027')
        ->assertSee('2027年')
        ->call('nextYear')
        ->assertSet('year', '2028')
        ->call('previousYear')
        ->call('previousYear')
        ->assertSet('year', '2026');

    Livewire::withQueryParams(['year' => 'abc'])->test(CompanyCalendarSettings::class)->assertSet('year', '2026');
    Livewire::withQueryParams(['year' => '1999'])->test(CompanyCalendarSettings::class)->assertSet('year', '2026');
    Livewire::withQueryParams(['year' => '2100'])->test(CompanyCalendarSettings::class)->call('nextYear')->assertSet('year', '2100');
});

it('replaces the year with the 初期データ after the confirmation, leaving the other years alone', function () {
    $calendar = app(CompanyCalendar::class);
    $calendar->setDay(CarbonImmutable::parse('2026-06-01'), CalendarDayType::CompanyHoliday); // overwritten
    $calendar->setDay(CarbonImmutable::parse('2027-01-04'), CalendarDayType::CompanyHoliday); // kept
    $this->actingAs($this->admin);

    Livewire::test(CompanyCalendarSettings::class)
        ->assertActionVisible('importSeed')
        ->mountAction('importSeed')
        ->assertMountedActionModalSee(['2026年の設定を初期データで上書きします。よろしいですか？', '上書きする'])
        ->callMountedAction()
        ->assertNotified('初期データを読み込みました')
        ->assertSet('version', 1);

    $days = $calendar->markedDays(2026);
    expect($days)->toHaveCount(count(SeedCalendars::for(2026)))
        ->and($days['2026-01-12'])->toBe(CalendarDayType::NationalHoliday) // 成人の日
        ->and($days['2026-08-13'])->toBe(CalendarDayType::CompanyHoliday) // お盆
        ->and($days['2026-11-07'])->toBe(CalendarDayType::HalfDay)
        ->and($days)->not->toHaveKey('2026-06-01')
        ->and($calendar->markedDays(2027))->toBe(['2027-01-04' => CalendarDayType::CompanyHoliday]);

    // no 初期データ for 2027
    Livewire::withQueryParams(['year' => '2027'])->test(CompanyCalendarSettings::class)->assertActionHidden('importSeed');
});

it('lists the calendar in the settings menu with the closed days of this year', function () {
    app(CompanyCalendar::class)->importSeed(2026);
    $this->actingAs($this->admin);

    // 52 Sundays + 16 祝日 + 27 会社休日 (none of them on a Sunday)
    Livewire::test(SettingsMenuWidget::class)->assertSee(['会社カレンダー', '2026年 休日 95 日']);
});

it('keeps employees out of the calendar', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings/calendar')->assertForbidden();
});
