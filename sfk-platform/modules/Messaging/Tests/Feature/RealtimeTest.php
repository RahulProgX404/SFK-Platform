<?php

/*
 * Real time over WebSockets (docs/realtime.md, Laravel Reverb): 通知 and 社内メッセージ are pushed on private channels —
 * a user's own `sfk.user.<id>`, a conversation's `sfk.conversation.<id>` (members only) — after the commit, carrying
 * versions and ids but never a message; a page may subscribe only where the app says so; an unreachable Reverb never
 * breaks a request (the pages keep polling).
 */

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Application\Realtime\Realtime;
use Modules\Core\Filament\Support\ThemeAssets;
use Modules\Messaging\Http\MessengerPulse;
use Modules\Messaging\Models\Message;
use Tests\Support\RecordingBroadcaster;

afterEach(fn () => Realtime::reset());

function realtimeDirect(User $a, User $b): int
{
    return (int) test()->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
}

it('pushes nothing while real time is off', function () {
    [$a, $b] = User::factory()->count(2)->create();
    expect(Realtime::enabled())->toBeFalse()->and(Realtime::clientConfig())->toBeNull();

    $conversation = realtimeDirect($a, $b);
    $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => 'やあ', 'client_id' => (string) Str::uuid()])->assertCreated();
});

it('pushes a new message to the conversation and the members\' inboxes, without its text', function () {
    $pushes = RecordingBroadcaster::install();
    [$a, $b, $outsider] = User::factory()->count(3)->create();
    $conversation = realtimeDirect($a, $b);
    $pushes->pushes = [];

    $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => '社外秘の見積', 'client_id' => (string) Str::uuid()])->assertCreated();

    expect($pushes->to('sfk.conversation.'.$conversation, 'changed'))->toHaveCount(1)
        ->and($pushes->to('sfk.user.'.$b->id, 'inbox'))->toHaveCount(1)
        ->and($pushes->to('sfk.user.'.$a->id, 'inbox'))->toHaveCount(1)
        ->and($pushes->to('sfk.user.'.$outsider->id))->toBe([])
        ->and(json_encode($pushes->pushes, JSON_UNESCAPED_UNICODE))->not->toContain('社外秘');

    // read marks and 入力中 on the conversation's channel
    $message = Message::query()->latest('id')->value('id');
    $this->actingAs($b)->postJson(route('messaging.conversations.read', ['conversation' => $conversation]), ['message' => $message])->assertOk();
    $this->post(MessengerPulse::url($b->id, $conversation, $a->id), ['typing' => true])->assertOk();

    expect($pushes->to('sfk.conversation.'.$conversation, 'read'))->toHaveCount(1)
        ->and(array_column($pushes->to('sfk.conversation.'.$conversation, 'typing'), 'payload'))->toContain(['user' => $b->id, 'typing' => true]);
});

it('pushes nothing of a rolled-back change', function () {
    $pushes = RecordingBroadcaster::install();
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = realtimeDirect($a, $b);
    $pushes->pushes = [];

    try {
        DB::transaction(function (): void {
            Realtime::send(['sfk.user.1'], 'inbox', ['v' => 'x']);
            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    expect($pushes->pushes)->toBe([]);
});

it('pushes a 通知 to its user the moment it is stored', function () {
    $pushes = RecordingBroadcaster::install();
    app(NotificationKinds::class)->register(new NotificationKind(key: 'test.realtime', label: 'テスト'));
    app()->forgetInstance(Notifier::class);
    $user = User::factory()->create();

    app(Notifier::class)->send(Notice::make('test.realtime')->title('承認依頼')->url('http://localhost/erp/x'), [$user]);

    $push = $pushes->to('sfk.user.'.$user->id, 'notification');
    expect($push)->toHaveCount(1)
        ->and($push[0]['payload'])->toMatchArray(['t' => '承認依頼', 'u' => 'http://localhost/erp/x']);
});

it('keeps working when Reverb cannot be reached', function () {
    $pushes = RecordingBroadcaster::install();
    $pushes->fail = true;
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = realtimeDirect($a, $b);

    $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => '届く？', 'client_id' => (string) Str::uuid()])->assertCreated();
    expect(Message::query()->where('body', '届く？')->exists())->toBeTrue();
});

it('lets a page subscribe only to its own user channel and to conversations it is a member of', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
    ]);
    Broadcast::purge('reverb');
    Realtime::registerChannels();
    [$a, $b, $outsider] = User::factory()->count(3)->create();
    $conversation = realtimeDirect($a, $b);
    $auth = fn (User $user, string $channel) => $this->actingAs($user)->postJson(route('core.broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-'.$channel]);

    $auth($a, 'sfk.user.'.$a->id)->assertOk()->assertJsonStructure(['auth']);
    $auth($a, 'sfk.user.'.$b->id)->assertForbidden();
    $auth($b, 'sfk.conversation.'.$conversation)->assertOk();
    $auth($outsider, 'sfk.conversation.'.$conversation)->assertForbidden();

    // a member who left the group loses the channel too
    $group = (int) $this->actingAs($a)->postJson(route('messaging.groups.store'), ['name' => '現場', 'members' => [$b->id]])->json('id');
    $auth($b, 'sfk.conversation.'.$group)->assertOk();
    $this->actingAs($b)->postJson(route('messaging.conversations.leave', ['conversation' => $group]))->assertOk();
    $auth($b, 'sfk.conversation.'.$group)->assertForbidden();

    auth()->logout();
    $this->postJson(route('core.broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-sfk.user.'.$a->id])->assertUnauthorized();
});

it('tells the pages where to connect and lets the CSP allow the WebSocket', function () {
    RecordingBroadcaster::install();
    $user = User::factory()->create();
    grantDashboards($user, 'erp');

    $response = $this->actingAs($user)->get('/erp/calendar')->assertOk();

    expect($response->getContent())->toContain('name="sfk-realtime"')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('ws://localhost')
        ->and(ThemeAssets::bundle()['js'])->toContain('sfkRealtime');

    config(['broadcasting.public.host' => 'ws.example.jp', 'broadcasting.public.scheme' => 'https']);
    expect(Realtime::cspOrigins('erp.example.jp', true))->toBe(' wss://ws.example.jp');
});

it('puts メッセージ right after アプリ in the dashboard\'s top bar', function () {
    $user = User::factory()->create();
    grantDashboards($user, 'erp');

    $html = $this->actingAs($user)->get('/erp')->assertOk()->getContent();
    $apps = mb_strpos($html, 'sfk-apps-trigger');
    $messages = mb_strpos($html, 'data-sfk-messages-link');

    expect($apps)->not->toBeFalse()->and($messages)->not->toBeFalse()->and($messages)->toBeGreaterThan($apps);
});
