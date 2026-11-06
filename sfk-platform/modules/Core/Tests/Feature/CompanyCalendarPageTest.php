<?php

/*
 * 会社カレンダー for everyone (user 2026-11-06): read-only on the dashboard 白石封筒 (/erp/calendar), the first item of its
 * sidebar; only administrators change it (共通管理 → 会社カレンダー). On /portal, Space / Enter opens 白石封筒.
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Modules\Core\Application\Calendar\CompanyCalendar;
use Modules\Core\Domain\Calendar\CalendarDayType;
use Modules\Core\Filament\Calendar\CompanyCalendarPage;
use Modules\Core\Models\CompanyCalendarDay;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00'));
    $this->employee = User::factory()->create();
    grantDashboards($this->employee, 'erp');
});

it('shows every user the calendar read-only on the dashboard, from the sidebar', function () {
    $calendar = app(CompanyCalendar::class);
    $calendar->setDay(CarbonImmutable::parse('2026-08-13'), CalendarDayType::CompanyHoliday);
    $calendar->setDay(CarbonImmutable::parse('2026-11-07'), CalendarDayType::HalfDay);
    $this->actingAs($this->employee);

    // the sidebar of 白石封筒 leads to it
    $this->get('/erp')->assertOk()->assertSee(['会社カレンダー', 'href="'.CompanyCalendarPage::getUrl(panel: 'erp').'"'], false);

    $this->get('/erp/calendar')->assertOk()
        ->assertSeeInOrder(['会社カレンダー', '2026年', '1月', '12月', '＝祝日・日曜', '＝会社休日', '＝半日出勤（午前のみ）', '＝本日'])
        ->assertSee(['<span data-date="2026-08-13" title="会社休日"', 'sfk-cal-day is-company', 'sfk-cal-day is-half', 'title="祝日"' /* 日曜 */, 'data-date="2026-11-06" title="本日"', 'data-date="2026-11-05" title="平日"'], false)
        // nothing to click: no buttons on the days, no saving
        ->assertDontSee(['<button type="button" data-date=', 'setDay', '初期データを読み込む'], false);

    Filament::setCurrentPanel('erp');
    Livewire::withQueryParams(['year' => '2027'])->test(CompanyCalendarPage::class)
        ->assertSee('2027年')
        ->call('previousYear')
        ->assertSet('year', '2026');

    // the page has no way to change a day
    expect(method_exists(CompanyCalendarPage::class, 'setDay'))->toBeFalse()
        ->and(CompanyCalendarDay::query()->count())->toBe(2);

    // the editor stays with the administrators
    $this->get('/settings/calendar')->assertForbidden();
});

it('opens 白石封筒 with Space / Enter on the portal', function () {
    $admin = User::factory()->superAdmin()->create(); // 白石封筒 and 共通管理: the portal is shown

    $this->actingAs($admin)->get('/portal')->assertOk()
        ->assertViewHas('keyboardUrl', Filament::getPanel('erp')->getUrl())
        ->assertSee(['window.location.assign(url);', "event.key !== 'Enter' && event.key !== ' '"], false)
        // the URL is followed by ";" (PHP drops the line break after it, without it the script does not run)
        ->assertSee('const url = '.Js::from(Filament::getPanel('erp')->getUrl())->toHtml().';', false);
});
