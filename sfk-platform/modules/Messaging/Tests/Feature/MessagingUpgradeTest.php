<?php

/*
 * 社内メッセージ upgrade (docs/messaging.md, messaging-system.md 2026-11-18): channels (部署・プロジェクト, read-only,
 * public / invite-only, archive), priorities and notification levels (one 通知 per member and message), replies,
 * moderation by a 管理者 with an audit, 未読にする / すべて既読, the order of pinned conversations, 日付へ移動, shared links,
 * メッセージ情報, search filters, presence (離席中 / 取り込み中), company settings and メッセージ管理.
 */

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Notifications\NotificationPulse;
use Modules\Core\Models\EmployeeCategory;
use Modules\Messaging\Application\MessagingSettings;
use Modules\Messaging\Application\Signals;
use Modules\Messaging\Filament\Pages\MessagingAdminPage;
use Modules\Messaging\Http\MessengerPulse;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageEdit;
use Modules\Messaging\Models\MessagingAuditLog;
use Modules\Messaging\Models\Participant;

function upgradeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['user_type' => UserType::Admin])->save();

    return $admin;
}

function upgradeSay(User $from, int $conversation, array $payload): TestResponse
{
    return test()->actingAs($from)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['client_id' => (string) Str::uuid(), ...$payload]);
}

function upgradeChannel(User $creator, array $input): int
{
    return (int) test()->actingAs($creator)->postJson(route('messaging.channels.store'), ['kind' => 'department', 'public' => true, 'post' => 'everyone', ...$input])->assertCreated()->json('id');
}

it('lets administrators create channels by policy, with the members of chosen 従業員カテゴリ', function () {
    $admin = upgradeAdmin();
    [$a, $b, $outside] = User::factory()->count(3)->create();
    $category = EmployeeCategory::createAtEnd('製造 '.Str::random(4));
    $a->employeeCategories()->attach($category);
    $b->employeeCategories()->attach($category);

    $this->actingAs($a)->postJson(route('messaging.channels.store'), ['name' => '製造部', 'kind' => 'department', 'post' => 'everyone'])->assertForbidden();

    $channel = upgradeChannel($admin, ['name' => '製造部', 'categories' => [$category->id]]);
    $members = Participant::query()->where('conversation_id', $channel)->whereNull('left_at')->pluck('user_id')->all();
    expect($members)->toEqualCanonicalizing([$admin->id, $a->id, $b->id])
        ->and(MessagingAuditLog::query()->where('action', 'channel.created')->where('conversation_id', $channel)->exists())->toBeTrue()
        ->and($this->actingAs($a)->getJson(route('messaging.inbox'))->json('conversations.0'))->toMatchArray(['type' => 'channel', 'name' => '製造部', 'kind' => 'department']);

    // the company may let everyone create channels
    grantDashboards($admin, 'erp');
    $this->actingAs($admin)->get(MessagingAdminPage::getUrl(panel: 'erp'))->assertOk();
    MessagingSettings::save($admin, ['channel_creators' => 'everyone']);
    upgradeChannel($outside, ['name' => '新製品プロジェクト', 'kind' => 'project']);
});

it('keeps read-only and archived channels free of posts, and lets employees find and join public ones', function () {
    $admin = upgradeAdmin();
    [$member, $other] = User::factory()->count(2)->create();
    $news = upgradeChannel($admin, ['name' => '社内報', 'post' => 'admins', 'members' => [$member->id]]);

    upgradeSay($member, $news, ['body' => '書き込み'])->assertForbidden();
    upgradeSay($admin, $news, ['body' => '今月の社内報です'])->assertCreated();
    $this->actingAs($member)->getJson(route('messaging.inbox'))->assertJsonPath('conversations.0.readonly', true);

    // public: found and joined; invite-only: neither
    $found = collect($this->actingAs($other)->getJson(route('messaging.channels.index'))->json('channels'));
    expect($found->firstWhere('id', $news))->toMatchArray(['joined' => false, 'readonly' => true]);
    $this->actingAs($other)->postJson(route('messaging.channels.join', ['channel' => $news]))->assertOk();
    $this->actingAs($other)->getJson(route('messaging.conversations.show', ['conversation' => $news]))->assertOk();

    $secret = upgradeChannel($admin, ['name' => '経営会議', 'public' => false]);
    expect(collect($this->actingAs($other)->getJson(route('messaging.channels.index'))->json('channels'))->pluck('id'))->not->toContain($secret);
    $this->actingAs($other)->postJson(route('messaging.channels.join', ['channel' => $secret]))->assertNotFound();

    // archived: still readable, no new messages; only the channel's 管理者 or a company administrator archives
    $this->actingAs($member)->postJson(route('messaging.channels.archive', ['channel' => $news]), ['archive' => true])->assertForbidden();
    $this->actingAs($admin)->postJson(route('messaging.channels.archive', ['channel' => $news]), ['archive' => true])->assertOk();
    upgradeSay($admin, $news, ['body' => 'もう投稿できない'])->assertForbidden();
    $this->actingAs($member)->getJson(route('messaging.conversations.show', ['conversation' => $news]))->assertOk()->assertJsonPath('conversation.archived', true);
});

it('sends one 通知 per member and message, for its strongest reason, under each member\'s level', function () {
    $owner = User::factory()->create();
    [$all, $mentionsOnly, $none, $muted] = User::factory()->count(4)->create();
    $group = (int) $this->actingAs($owner)->postJson(route('messaging.groups.store'), ['name' => '現場', 'members' => [$all->id, $mentionsOnly->id, $none->id, $muted->id]])->json('id');
    $level = fn (User $user, string $value) => $this->actingAs($user)->postJson(route('messaging.conversations.state', ['conversation' => $group]), ['state' => 'level', 'value' => $value])->assertOk();
    $level($mentionsOnly, 'mentions');
    $level($none, 'none');
    $this->actingAs($muted)->postJson(route('messaging.conversations.state', ['conversation' => $group]), ['state' => 'muted', 'value' => 8])->assertOk();
    $count = fn (User $user): int => DatabaseNotification::query()->where('notifiable_id', $user->id)->count();

    // a normal message: no bell for anyone (toasts come from the inbox)
    upgradeSay($owner, $group, ['body' => 'おはようございます'])->assertCreated();
    expect([$count($all), $count($mentionsOnly), $count($none), $count($muted)])->toBe([0, 0, 0, 0]);

    // 重要: everyone whose level lets it through; a temporary 通知オフ and 通知しない hold it back
    upgradeSay($owner, $group, ['body' => '検査日程の変更', 'priority' => 1])->assertCreated();
    expect([$count($all), $count($mentionsOnly), $count($none), $count($muted)])->toBe([1, 1, 0, 0])
        ->and(DatabaseNotification::query()->where('notifiable_id', $all->id)->value('data')['title'])->toContain('【重要】')
        // its button says what it opens, in the bell and in the toast
        ->and(DatabaseNotification::query()->where('notifiable_id', $all->id)->value('data')['actions'][0]['label'])->toBe('トークを開く')
        ->and(NotificationPulse::current($all->id)['l'] ?? null)->toBe('トークを開く');

    // a mention that is also 至急: one 通知 (the mention), and it reaches the muted member but not 通知しない
    upgradeSay($owner, $group, ['body' => "@{$muted->name} @{$none->name} 至急確認", 'priority' => 2, 'mentions' => [$muted->id, $none->id]])->assertCreated();
    expect([$count($muted), $count($none)])->toBe([1, 0])
        ->and(DatabaseNotification::query()->where('notifiable_id', $muted->id)->latest()->value('data')['title'])->toContain('メンション');
});

it('tells the author when someone replies to their message', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $direct = (int) $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
    $question = upgradeSay($a, $direct, ['body' => '在庫はありますか'])->json('message.id');

    upgradeSay($b, $direct, ['body' => 'あります', 'reply_to_id' => $question])->assertCreated();
    $notice = DatabaseNotification::query()->where('notifiable_id', $a->id)->first();
    expect($notice?->data['title'])->toContain('返信')
        ->and(json_encode($notice?->data, JSON_UNESCAPED_UNICODE))->not->toContain('あります');
});

it('lets a group\'s 管理者 remove a member\'s message, never in a direct conversation, with an audit', function () {
    [$owner, $member] = User::factory()->count(2)->create();
    $group = (int) $this->actingAs($owner)->postJson(route('messaging.groups.store'), ['name' => '総務', 'members' => [$member->id]])->json('id');
    $message = upgradeSay($member, $group, ['body' => '不適切な投稿'])->json('message.id');
    $mine = upgradeSay($owner, $group, ['body' => '管理者の投稿'])->json('message.id');

    $this->actingAs($member)->postJson(route('messaging.messages.moderate', ['message' => $mine]))->assertForbidden();
    $removed = $this->actingAs($owner)->postJson(route('messaging.messages.moderate', ['message' => $message]))->assertOk()->json('message');
    expect($removed)->toMatchArray(['unsent' => true, 'moderated' => true, 'body' => null])
        ->and(MessageEdit::query()->where('message_id', $message)->value('action'))->toBe('moderate')
        ->and(MessagingAuditLog::query()->where('action', 'message.moderated')->value('details'))->toMatchArray(['message' => $message, 'author' => $member->id]);

    $direct = (int) $this->actingAs($owner)->postJson(route('messaging.direct'), ['user' => $member->id])->json('id');
    $private = upgradeSay($member, $direct, ['body' => '個人的な話'])->json('message.id');
    $this->actingAs($owner)->postJson(route('messaging.messages.moderate', ['message' => $private]))->assertForbidden();
});

it('marks a conversation unread, everything read, and keeps the order of pinned conversations', function () {
    $me = User::factory()->create();
    $others = User::factory()->count(3)->create();
    $ids = [];
    foreach ($others as $other) {
        $id = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');
        upgradeSay($other, $id, ['body' => 'こんにちは'])->assertCreated();
        $ids[] = $id;
    }

    $this->actingAs($me)->postJson(route('messaging.read-all'))->assertOk()->assertJsonPath('read', 3);
    expect(collect($this->actingAs($me)->getJson(route('messaging.inbox'))->json('conversations'))->sum('unread'))->toBe(0);

    $this->actingAs($me)->postJson(route('messaging.conversations.unread', ['conversation' => $ids[1]]))->assertOk();
    expect(collect($this->actingAs($me)->getJson(route('messaging.inbox'))->json('conversations'))->firstWhere('id', $ids[1])['unread'])->toBe(1);

    foreach ($ids as $id) {
        $this->actingAs($me)->postJson(route('messaging.conversations.state', ['conversation' => $id]), ['state' => 'pinned', 'value' => true])->assertOk();
    }
    $this->actingAs($me)->postJson(route('messaging.pins.order'), ['ids' => [$ids[2], $ids[0], $ids[1]]])->assertOk();
    $order = collect($this->actingAs($me)->getJson(route('messaging.inbox'))->json('conversations'))->sortBy('pin_order')->pluck('id')->values()->all();
    expect($order)->toBe([$ids[2], $ids[0], $ids[1]]);
});

it('jumps to a date, lists shared links and tells the sender who received and read a message', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $direct = (int) $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
    $this->travelTo(now()->subDays(3));
    $old = upgradeSay($a, $direct, ['body' => '三日前の話 https://example.com/spec?id=1'])->json('message.id');
    $this->travelBack();
    $new = upgradeSay($a, $direct, ['body' => '今日の話'])->json('message.id');

    $jump = $this->actingAs($b)->getJson(route('messaging.conversations.date', ['conversation' => $direct, 'date' => now()->subDays(3)->toDateString()]))->assertOk()->json();
    expect($jump['target'])->toBe($old);

    $links = $this->actingAs($b)->getJson(route('messaging.conversations.links', ['conversation' => $direct]))->json('items');
    expect($links[0])->toMatchArray(['url' => 'https://example.com/spec?id=1', 'host' => 'example.com', 'message' => $old]);

    // B has not fetched the newest message yet: not delivered
    expect($this->actingAs($a)->getJson(route('messaging.messages.info', ['message' => $new]))->json('info'))->toMatchArray(['mine' => true, 'read' => [], 'delivered' => [], 'waiting' => [$b->id]]);

    // B opens the conversation (delivered) and reads only the older message
    $this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $direct]))->assertOk();
    $this->actingAs($b)->postJson(route('messaging.conversations.read', ['conversation' => $direct]), ['message' => $old])->assertOk();
    $info = $this->actingAs($a)->getJson(route('messaging.messages.info', ['message' => $new]))->json('info');
    expect($info)->toMatchArray(['mine' => true, 'read' => [], 'delivered' => [$b->id], 'waiting' => []]);
    // someone else's message: no reading list
    expect($this->actingAs($b)->getJson(route('messaging.messages.info', ['message' => $new]))->json('info.read'))->toBeNull();
});

it('filters the search by type: direct, groups, channels, documents, links and pinned', function () {
    [$me, $other] = User::factory()->count(2)->create();
    $direct = (int) $this->actingAs($me)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');
    $group = (int) $this->actingAs($me)->postJson(route('messaging.groups.store'), ['name' => '営業', 'members' => [$other->id]])->json('id');
    upgradeSay($other, $direct, ['body' => '見積の件 https://example.com'])->assertCreated();
    $pinned = upgradeSay($other, $group, ['body' => '見積の件（グループ）'])->json('message.id');
    $this->actingAs($me)->postJson(route('messaging.messages.pin', ['message' => $pinned]), ['pin' => true])->assertOk();

    $search = fn (array $filters) => collect($this->actingAs($me)->getJson(route('messaging.search', ['q' => '見積', ...$filters]))->json('results'))->pluck('conversation')->all();
    expect($search(['kind' => 'direct']))->toBe([$direct])
        ->and($search(['kind' => 'group']))->toBe([$group])
        ->and($search(['kind' => 'links']))->toBe([$direct])
        ->and($search(['kind' => 'pinned']))->toBe([$group]);
});

it('shows away and do-not-disturb, and hides read marks and presence when the company switches them off', function () {
    $admin = upgradeAdmin();
    [$a, $b] = User::factory()->count(2)->create();
    $direct = (int) $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
    $pulse = fn () => $this->get(MessengerPulse::url($a->id, $direct, $b->id))->json('o.state');

    Signals::seen($b->id);
    expect($pulse())->toBe('online');
    Signals::seen($b->id, idle: true);
    expect($pulse())->toBe('away');
    $this->actingAs($b)->postJson(route('messaging.preferences'), ['status' => 'dnd'])->assertOk()->assertJsonPath('prefs.status', 'dnd');
    Signals::seen($b->id);
    expect($pulse())->toBe('dnd');

    $message = upgradeSay($a, $direct, ['body' => '読んだ？'])->json('message.id');
    $this->actingAs($b)->postJson(route('messaging.conversations.read', ['conversation' => $direct]), ['message' => $message])->assertOk();
    expect(collect($this->actingAs($a)->getJson(route('messaging.conversations.reads', ['conversation' => $direct]))->json('reads'))->firstWhere('user', $b->id)['read'])->toBe($message);

    MessagingSettings::save($admin, ['read_receipts' => false, 'presence' => false]);
    expect(collect($this->actingAs($a)->getJson(route('messaging.conversations.reads', ['conversation' => $direct]))->json('reads'))->firstWhere('user', $b->id)['read'])->toBe(0)
        ->and($pulse())->toBe('offline');
});

it('keeps メッセージ管理 for administrators, with figures and an audit but no message texts', function () {
    $admin = upgradeAdmin();
    [$a, $b] = User::factory()->count(2)->create();
    grantDashboards($a, 'erp');
    $direct = (int) $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
    upgradeSay($a, $direct, ['body' => '極秘の相談'])->assertCreated();

    $this->actingAs($a)->get(MessagingAdminPage::getUrl(panel: 'erp'))->assertForbidden();
    grantDashboards($admin, 'erp');
    $page = Livewire::actingAs($admin)->test(MessagingAdminPage::class);
    $page->set('tab', 'stats')->assertSee('メッセージ（30日）')->assertDontSee('極秘の相談');
    $page->set('settings.edit_window_minutes', 30)->call('save');
    expect(config('messaging.edit_window_minutes'))->toBe(30)
        ->and(MessagingAuditLog::query()->where('action', 'settings.updated')->exists())->toBeTrue();
    $page->set('tab', 'audit')->assertSee('設定を変更');

    $page->set('settings.retention_days', 5)->call('save')->assertHasErrors('settings.retention_days');
});

it('publishes announcements with a priority, an end date and the members of chosen groups', function () {
    $admin = upgradeAdmin();
    [$a, $b, $c] = User::factory()->count(3)->create();
    $group = (int) $this->actingAs($admin)->postJson(route('messaging.groups.store'), ['name' => '工場', 'members' => [$a->id, $b->id]])->json('id');

    $id = $this->actingAs($admin)->postJson(route('messaging.announcements.store'), [
        'title' => '設備点検', 'body' => '明日は停止します。', 'priority' => 'urgent', 'audience' => 'groups', 'audience_ids' => [$group],
        'expires_at' => now()->addDay()->toDateTimeString(),
    ])->assertCreated()->json('id');

    expect(DatabaseNotification::query()->whereIn('notifiable_id', [$a->id, $b->id])->count())->toBe(2)
        ->and(DatabaseNotification::query()->where('notifiable_id', $c->id)->exists())->toBeFalse()
        ->and(DatabaseNotification::query()->where('notifiable_id', $a->id)->value('data')['title'])->toStartWith('【至急】');

    $this->actingAs($a)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->assertOk();
    $people = $this->actingAs($admin)->getJson(route('messaging.announcements.show', ['announcement' => $id]))->json('announcement.stats.people');
    expect(collect($people)->firstWhere('user', $a->id)['read_at'])->not->toBeNull()
        ->and(collect($people)->firstWhere('user', $b->id)['read_at'])->toBeNull();

    // searchable, and gone from the recipients' list after its end
    expect($this->actingAs($admin)->getJson(route('messaging.announcements.index', ['q' => '点検']))->json('announcements'))->toHaveCount(1);
    $this->travel(2)->days();
    expect($this->actingAs($b)->getJson(route('messaging.announcements.index'))->json('announcements'))->toBe([])
        ->and($this->actingAs($b)->getJson(route('messaging.unread'))->json('announcements'))->toBe(0);
});

it('keeps forwarding out of read-only channels', function () {
    $admin = upgradeAdmin();
    $member = User::factory()->create();
    $news = upgradeChannel($admin, ['name' => '社内報', 'post' => 'admins', 'members' => [$member->id]]);
    $direct = (int) $this->actingAs($member)->postJson(route('messaging.direct'), ['user' => $admin->id])->json('id');
    $message = upgradeSay($member, $direct, ['body' => '転送したい'])->json('message.id');

    $this->actingAs($member)->postJson(route('messaging.messages.forward', ['message' => $message]), ['conversations' => [$news]])->assertForbidden();
    expect(Message::query()->where('conversation_id', $news)->where('body', '転送したい')->exists())->toBeFalse();
});
