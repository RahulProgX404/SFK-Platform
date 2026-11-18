<?php

/*
 * 社内メッセージ — the page, search, announcements and speed (docs/messaging.md): the messenger is in the ERP
 * dashboard's sidebar with its own assets only on its page; the unread badge rides on Core's pulse; search reads only
 * what the user may read; announcements are published by administrators to a fixed audience; the inbox costs a fixed
 * number of queries whatever the number of conversations.
 */

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Notifications\NotificationPulse;
use Modules\Core\Application\Notifications\UnreadCounters;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Core\Filament\Support\ThemeAssets;
use Modules\Core\Models\EmployeeCategory;
use Modules\Messaging\Filament\Pages\MessengerPage;
use Modules\Messaging\Http\MessengerAssets;
use Modules\Messaging\Models\AnnouncementRecipient;
use Modules\Messaging\Models\MessageAttachment;

function messagingAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['user_type' => UserType::Admin])->save();

    return $admin;
}

function messagingTalk(User $from, int $conversation, string $body): array
{
    return test()->actingAs($from)
        ->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => $body, 'client_id' => (string) Str::uuid()])
        ->assertCreated()->json('message');
}

it('shows the messenger in the ERP dashboard with its own assets and the user\'s inbox', function () {
    [$me, $colleague] = User::factory()->count(2)->create();
    grantDashboards($me, 'erp');
    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $colleague->id])->json('id');
    messagingTalk($colleague, $conversation, 'お疲れ様です');

    $html = $this->actingAs($me)->get(MessengerPage::getUrl(panel: 'erp'))->assertOk()->getContent();

    expect($html)->toContain('sfkMessenger(')
        ->and($html)->toContain(route('messaging.assets.js', ['v' => MessengerAssets::version()]))
        ->and($html)->toContain('お疲れ様です')
        ->and($html)->toContain('name="sfk-messaging"');

    // every other page: the badge meta, not the messenger's script
    $home = $this->actingAs($me)->get('/erp/calendar')->assertOk()->getContent();
    expect($home)->toContain('name="sfk-messaging"')->and($home)->not->toContain('messaging/assets/messenger.js')
        ->and(ThemeAssets::bundle()['js'])->toContain('sfkMessagingBadge');
});

it('puts the inbox version into Core\'s pulse', function () {
    [$me, $colleague] = User::factory()->count(2)->create();
    $before = $this->get(NotificationPulse::url($me->id))->assertOk()->json('x.m');

    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $colleague->id])->json('id');
    messagingTalk($colleague, $conversation, '新着');

    expect($this->get(NotificationPulse::url($me->id))->json('x.m'))->not->toBe($before);
});

it('serves the assets with a long cache for their version', function () {
    $this->get(route('messaging.assets.js', ['v' => MessengerAssets::version()]))->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    expect($this->get(route('messaging.assets.css'))->headers->get('Content-Type'))->toStartWith('text/css');
});

it('loads the inbox in a fixed number of queries', function () {
    $me = User::factory()->create();
    $count = function () use ($me): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($me)->getJson(route('messaging.inbox'))->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    $talk = function () use ($me): void {
        $other = User::factory()->create();
        $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');
        messagingTalk($other, $conversation, 'hi');
    };

    $talk();
    $few = $count();
    foreach (range(1, 6) as $i) {
        $talk();
    }
    expect($count())->toBe($few)->and($few)->toBeLessThanOrEqual(12);
});

it('loads a page of history in a fixed number of queries', function () {
    [$me, $other] = User::factory()->count(2)->create();
    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');
    foreach (range(1, 30) as $i) {
        $message = messagingTalk($other, $conversation, "本文{$i}");
        $this->actingAs($me)->postJson(route('messaging.messages.react', ['message' => $message['id']]), ['emoji' => '👍']);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($me)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(14);
});

it('searches only the messages the user may read, by words, sender and period', function () {
    [$me, $colleague, $stranger] = User::factory()->count(3)->create();
    $ours = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $colleague->id])->json('id');
    $theirs = (int) $this->actingAs($colleague)->postJson(route('messaging.direct'), ['user' => $stranger->id])->json('id');
    messagingTalk($colleague, $ours, '見積書を送りました');
    $unsent = messagingTalk($colleague, $ours, '見積書の間違い');
    $this->actingAs($colleague)->postJson(route('messaging.messages.unsend', ['message' => $unsent['id']]));
    messagingTalk($stranger, $theirs, '見積書は社外秘');

    $results = $this->actingAs($me)->getJson(route('messaging.search', ['q' => '見積書']))->assertOk()->json('results');
    expect(collect($results)->pluck('text')->all())->toBe(['見積書を送りました'])
        ->and($results[0]['conversation'])->toBe($ours)
        ->and($results[0]['conversation_name'])->toBe($colleague->name);

    // folded like the list search (全角 / 半角)
    messagingTalk($colleague, $ours, 'ＡＢＣ株式会社の件');
    expect($this->actingAs($me)->getJson(route('messaging.search', ['q' => 'abc']))->json('results.0.text'))->toContain('株式会社');

    expect($this->actingAs($me)->getJson(route('messaging.search', ['user' => $me->id]))->json('results'))->toBe([])
        ->and($this->actingAs($me)->getJson(route('messaging.search', ['q' => '見積書', 'to' => now()->subDay()->toDateString()]))->json('results'))->toBe([]);
});

it('lets only administrators publish announcements, to a fixed audience that reads and confirms', function () {
    $admin = messagingAdmin();
    [$sales, $factory] = User::factory()->count(2)->create();
    $category = EmployeeCategory::createAtEnd('営業 '.Str::random(4));
    $sales->employeeCategories()->attach($category);

    $this->actingAs($sales)->postJson(route('messaging.announcements.store'), ['title' => '勝手に', 'body' => '本文', 'audience' => 'all'])->assertForbidden();

    $id = $this->actingAs($admin)->postJson(route('messaging.announcements.store'), [
        'title' => '年末年始の休業',
        'body' => '12月29日から休業します。',
        'urgent' => true,
        'requires_confirmation' => true,
        'audience' => 'categories',
        'audience_ids' => [$category->id],
    ])->assertCreated()->json('id');

    expect(AnnouncementRecipient::query()->where('announcement_id', $id)->pluck('user_id')->all())->toEqualCanonicalizing([$sales->id])
        ->and(DatabaseNotification::query()->where('notifiable_id', $sales->id)->value('data')['title'])->toBe('【重要】全社アナウンス: 年末年始の休業')
        ->and($this->actingAs($sales)->getJson(route('messaging.unread'))->json('announcements'))->toBe(1);

    // not sent to the factory: nothing to read
    $this->actingAs($factory)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->assertNotFound();
    expect($this->actingAs($factory)->getJson(route('messaging.announcements.index'))->json('announcements'))->toBe([]);

    $shown = $this->actingAs($sales)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->assertOk()->json('announcement');
    expect($shown)->toMatchArray(['title' => '年末年始の休業', 'priority' => 'important', 'confirm' => true, 'confirmed' => false, 'stats' => null]);
    $this->actingAs($sales)->postJson(route('messaging.announcements.confirm', ['announcement' => $id]))->assertOk();

    $stats = $this->actingAs($admin)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->json('announcement.stats');
    expect($stats)->toMatchArray(['total' => 1, 'read' => 1, 'confirmed' => 1, 'unread_users' => []]);
});

it('notifies a scheduled announcement once its time came', function () {
    $admin = messagingAdmin();
    $employee = User::factory()->create();

    $id = $this->actingAs($admin)->postJson(route('messaging.announcements.store'), [
        'title' => '来週の安全大会', 'body' => '全員参加です。', 'audience' => 'users', 'audience_ids' => [$employee->id],
        'published_at' => now()->addHour()->toDateTimeString(),
    ])->assertCreated()->json('id');

    expect(DatabaseNotification::query()->where('notifiable_id', $employee->id)->exists())->toBeFalse();
    $this->actingAs($employee)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->assertNotFound();

    $this->travel(61)->minutes();
    $this->artisan('messaging:announcements')->assertSuccessful();
    expect(DatabaseNotification::query()->where('notifiable_id', $employee->id)->exists())->toBeTrue();
    $this->actingAs($employee)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->assertOk();
});

it('offers the built-in stickers and sends one', function () {
    [$me, $other] = User::factory()->count(2)->create();
    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');

    $message = $this->actingAs($me)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['sticker' => 'b:arigato', 'client_id' => (string) Str::uuid()])->assertCreated()->json('message');
    expect($message)->toMatchArray(['type' => 'sticker', 'sticker' => 'b:arigato']);

    $this->actingAs($me)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['sticker' => 'b:unknown'])->assertStatus(422);
    // company stickers are for administrators to add
    $this->actingAs($me)->postJson(route('messaging.stickers.store'), ['name' => 'x', 'file' => UploadedFile::fake()->image('s.png')])->assertForbidden();
});

it('removes unsent uploads at night', function () {
    Storage::fake(config('filesystems.uploads', 'local'));
    [$me, $other] = User::factory()->count(2)->create();
    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');
    $upload = $this->actingAs($me)->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->image('a.png')])->json('attachment');

    $this->travel(25)->hours();
    $this->artisan('messaging:prune')->assertSuccessful();

    expect(MessageAttachment::query()->find($upload['id']))->toBeNull();
});

it('counts unread messages next to the unread 通知 on the portal and keeps them in step (user 2026-11-18)', function () {
    $me = User::factory()->superAdmin()->create();
    $colleague = User::factory()->create();
    $conversation = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $colleague->id])->json('id');

    $this->actingAs($me)->get('/portal')->assertOk()
        ->assertSee('未読の通知はありません')
        ->assertSee('data-sp-unread-item="messages"', false)
        ->assertSee(NotificationPulse::url($me->id), false);
    $this->actingAs($me)->getJson(route('core.portal.unread'))->assertOk()
        ->assertJson(['items' => [['key' => 'notifications', 'count' => 0], ['key' => 'messages', 'count' => 0]]]);

    messagingTalk($colleague, $conversation, '確認お願いします');
    messagingTalk($colleague, $conversation, 'よろしくお願いします');

    $this->actingAs($me)->get('/portal')->assertOk()
        ->assertSee('未読の通知があります')
        ->assertSeeInOrder(['未読のメッセージ', '2', '件'])
        ->assertSee(MessengerPage::getUrl(panel: 'erp'), false);
    $this->actingAs($me)->getJson(route('core.portal.unread'))->assertOk()
        ->assertJsonPath('items.1', ['key' => 'messages', 'count' => 2]);

    // guests: no counts
    auth()->logout();
    $this->getJson(route('core.portal.unread'))->assertUnauthorized();
});

it('keeps the portal open when a module\'s unread count fails', function () {
    $me = User::factory()->superAdmin()->create();
    UnreadCounters::register('broken', '壊れた', fn (User $user): int => throw new RuntimeException('no table'));

    try {
        $this->actingAs($me)->get('/portal')->assertOk()->assertSee('未読の通知はありません')->assertDontSee('壊れた');
        expect(array_column(UnreadCounters::for($me), 'key'))->toBe(['notifications', 'messages']);
    } finally {
        UnreadCounters::forget('broken');
    }
});

it('opens the messenger with the sidebar closed and a home button instead of the menu (user 2026-11-18)', function () {
    $me = User::factory()->create();
    grantDashboards($me, 'erp');

    expect(AppSidebarState::opensCollapsed([MessengerPage::class]))->toBeTrue();
    $html = $this->actingAs($me)->get(MessengerPage::getUrl(panel: 'erp'))->assertOk()->getContent();
    expect($html)->toContain(AppSidebarState::script([MessengerPage::class]))
        ->and($html)->toContain('aria-label="ホーム"')
        ->and($html)->not->toContain('openSidebar()');
});
