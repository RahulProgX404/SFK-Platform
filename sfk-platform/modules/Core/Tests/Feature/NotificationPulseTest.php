<?php

/*
 * 通知 in real time (user, 2026-11-16 — the bell showed a new 通知 only after a reload): a stored 通知 changes its
 * user's pulse after the commit (NotificationPulse), every panel page names the pulse, and the pulse address answers
 * from the cache alone — signed per user, without session or database.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Application\Notifications\NotificationPulse;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Filament\Support\ThemeAssets;

beforeEach(function () {
    app(NotificationKinds::class)->register(new NotificationKind(key: 'test.pulse', label: 'テスト通知'));
    app()->forgetInstance(Notifier::class);
});

function sendPulseNotice(User $to, ?User $actor = null, string $title = 'コメント'): void
{
    app(Notifier::class)->send(Notice::make('test.pulse')->title($title)->body('<b>確認</b>お願いします')->url('http://localhost/erp/partners/2'), [$to], $actor);
}

it('changes the pulse of who receives a 通知, with its title, plain body and link — never of the actor', function () {
    $actor = User::factory()->create();
    $colleague = User::factory()->create();
    expect(NotificationPulse::current($colleague->id))->toBeNull()
        ->and(NotificationPulse::version($colleague->id))->toBe('');

    sendPulseNotice($colleague, $actor);

    $pulse = NotificationPulse::current($colleague->id);
    expect($pulse)->not->toBeNull()
        ->and($pulse['t'])->toBe('コメント')
        ->and($pulse['b'])->toBe('確認お願いします')
        ->and($pulse['u'])->toBe('http://localhost/erp/partners/2')
        ->and(NotificationPulse::current($actor->id))->toBeNull();

    // every 通知 is a new version
    sendPulseNotice($colleague, $actor, '二通目');
    expect(NotificationPulse::current($colleague->id)['v'])->not->toBe($pulse['v'])
        ->and(NotificationPulse::current($colleague->id)['t'])->toBe('二通目');
});

it('announces a 通知 only once its transaction committed', function () {
    $colleague = User::factory()->create();

    try {
        DB::transaction(function () use ($colleague): void {
            sendPulseNotice($colleague);
            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }
    expect(NotificationPulse::current($colleague->id))->toBeNull();

    DB::transaction(fn () => sendPulseNotice($colleague));
    expect(NotificationPulse::current($colleague->id))->not->toBeNull();
});

it('answers the pulse address from the cache alone: signed per user, no session, no database', function () {
    $colleague = User::factory()->create();
    $other = User::factory()->create();
    sendPulseNotice($colleague);

    $this->get(route('core.pulse', ['user' => $colleague->id, 'key' => NotificationPulse::signature($other->id)]))->assertNotFound();
    $this->get(route('core.pulse', ['user' => $colleague->id, 'key' => str_repeat('0', 40)]))->assertNotFound();

    DB::enableQueryLog();
    $response = $this->get(NotificationPulse::url($colleague->id));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson(['v' => NotificationPulse::version($colleague->id), 't' => 'コメント'])
        ->assertCookieMissing(config('session.cookie'));
    expect($queries)->toBe([]);

    // nothing yet: an empty version
    // (other modules' channels ride along under "x", e.g. the messenger's inbox version — PulseChannels)
    $this->get(NotificationPulse::url($other->id))->assertOk()->assertJsonPath('v', '');
});

it('names the user\'s pulse on every panel page, and theme.js asks it and refreshes the bell', function () {
    Filament::setCurrentPanel('erp');
    $admin = User::factory()->superAdmin()->create();
    sendPulseNotice($admin);

    $this->actingAs($admin)->get('/erp')
        ->assertOk()
        ->assertSee('<meta name="sfk-notification-pulse" content="'.e(NotificationPulse::url($admin->id)).'" data-version="'.NotificationPulse::version($admin->id).'">', false);

    ThemeAssets::forget();
    $js = ThemeAssets::bundle()['js'];
    expect($js)->toContain('sfk-notification-pulse')
        ->toContain("window.Livewire?.dispatch('databaseNotificationsSent');")
        ->toContain('sfk-has-new-notice');

    // the bell no longer polls the database (the pulse refreshes it)
    expect(Filament::getPanel('erp')->getDatabaseNotificationsPollingInterval())->toBeNull();
});
