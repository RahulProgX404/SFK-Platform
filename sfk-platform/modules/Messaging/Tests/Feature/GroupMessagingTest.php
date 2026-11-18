<?php

/*
 * 社内メッセージ — groups (docs/messaging.md "Groups"): created by employees (company policy), managed by their 管理者
 * under the group's own rules; a removed or departed member loses the conversation, its history and its files; a
 * member added later reads the earlier history only when the group shares it; mentions notify.
 */

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Messaging\Domain\ParticipantRole;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\Participant;

beforeEach(function () {
    Storage::fake(config('filesystems.uploads', 'local'));
});

/** @param  list<User>  $members */
function messagingGroup(User $creator, array $members, string $name = '営業部'): int
{
    return (int) test()->actingAs($creator)
        ->postJson(route('messaging.groups.store'), ['name' => $name, 'members' => array_map(fn (User $user): int => $user->id, $members)])
        ->assertCreated()
        ->json('id');
}

function messagingSay(User $from, int $conversation, string $body, array $extra = []): array
{
    return test()->actingAs($from)
        ->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => $body, 'client_id' => (string) Str::uuid(), ...$extra])
        ->assertCreated()
        ->json('message');
}

it('creates a group with its creator as 管理者 and a system line', function () {
    [$owner, $a, $b] = User::factory()->count(3)->create();
    $group = messagingGroup($owner, [$a, $b]);

    expect(Participant::query()->where('conversation_id', $group)->where('user_id', $owner->id)->value('role'))->toBe(ParticipantRole::Admin)
        ->and(Participant::query()->where('conversation_id', $group)->count())->toBe(3)
        ->and(Message::query()->where('conversation_id', $group)->value('body'))->toBe("{$owner->name}さんがグループ「営業部」を作成しました");

    $inbox = $this->actingAs($a)->getJson(route('messaging.inbox'))->json('conversations.0');
    expect($inbox)->toMatchArray(['type' => 'group', 'name' => '営業部', 'members' => 3, 'admin' => false]);
});

it('lets only administrators create groups when the company says so', function () {
    config(['messaging.group_creators' => 'administrators']);
    [$employee, $other] = User::factory()->count(2)->create();

    $this->actingAs($employee)->postJson(route('messaging.groups.store'), ['name' => '勝手なグループ', 'members' => [$other->id]])->assertForbidden();
});

it('cuts a removed member off from new and old messages and files', function () {
    [$owner, $member] = User::factory()->count(2)->create();
    $group = messagingGroup($owner, [$member]);
    $upload = $this->actingAs($owner)->postJson(route('messaging.conversations.upload', ['conversation' => $group]), ['file' => UploadedFile::fake()->create('見積書.pdf', 20, 'application/pdf')]);
    $upload->assertStatus(422); // a fake PDF without PDF content is refused…
    $upload = $this->actingAs($owner)->postJson(route('messaging.conversations.upload', ['conversation' => $group]), ['file' => UploadedFile::fake()->createWithContent('見積書.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")])->assertCreated()->json('attachment');
    $old = messagingSay($owner, $group, '以前の話', ['attachment_ids' => [$upload['id']]]);

    // a member cannot remove others
    $this->actingAs($member)->deleteJson(route('messaging.conversations.members.remove', ['conversation' => $group, 'user' => $owner->id]))->assertForbidden();
    $this->actingAs($owner)->deleteJson(route('messaging.conversations.members.remove', ['conversation' => $group, 'user' => $member->id]))->assertOk();
    $new = messagingSay($owner, $group, '削除後の話');

    $this->actingAs($member);
    $this->getJson(route('messaging.conversations.show', ['conversation' => $group]))->assertNotFound();
    $this->getJson(route('messaging.conversations.sync', ['conversation' => $group, 'after' => $old['id']]))->assertNotFound();
    $this->get(route('messaging.attachments.show', ['attachment' => $upload['id']]))->assertNotFound();
    $this->postJson(route('messaging.messages.react', ['message' => $new['id']]), ['emoji' => '👍'])->assertNotFound();
    expect($this->getJson(route('messaging.inbox'))->json('conversations'))->toBe([])
        ->and($this->getJson(route('messaging.search', ['q' => '話']))->json('results'))->toBe([]);
});

it('shows the earlier history to members added later only when the group shares it', function () {
    [$owner, $a, $late, $later] = User::factory()->count(4)->create();
    $group = messagingGroup($owner, [$a]);
    messagingSay($owner, $group, '加入前の話');

    $this->actingAs($owner)->postJson(route('messaging.conversations.members.add', ['conversation' => $group]), ['users' => [$late->id]])->assertOk()->assertJsonPath('added', [$late->id]);
    expect(collect($this->actingAs($late)->getJson(route('messaging.conversations.show', ['conversation' => $group]))->json('messages'))->pluck('body'))->toContain('加入前の話');

    $this->actingAs($owner)->postJson(route('messaging.conversations.settings', ['conversation' => $group]), ['share_history' => false])->assertOk();
    $this->actingAs($owner)->postJson(route('messaging.conversations.members.add', ['conversation' => $group]), ['users' => [$later->id]])->assertOk();
    $seen = collect($this->actingAs($later)->getJson(route('messaging.conversations.show', ['conversation' => $group]))->json('messages'))->pluck('body');
    expect($seen)->not->toContain('加入前の話');
});

it('follows the group rules for members, info and pins', function () {
    [$owner, $member, $newcomer] = User::factory()->count(3)->create();
    $group = messagingGroup($owner, [$member]);
    $message = messagingSay($owner, $group, '大事なお知らせ');

    // default: everyone adds members and pins, only 管理者 change the group's info and settings
    $this->actingAs($member)->postJson(route('messaging.conversations.members.add', ['conversation' => $group]), ['users' => [$newcomer->id]])->assertOk();
    $this->actingAs($member)->postJson(route('messaging.messages.pin', ['message' => $message['id']]), ['pin' => true])->assertOk()->assertJsonPath('message.pinned', true);
    $this->actingAs($member)->postJson(route('messaging.conversations.info', ['conversation' => $group]), ['name' => '乗っ取り'])->assertForbidden();
    $this->actingAs($member)->postJson(route('messaging.conversations.settings', ['conversation' => $group]), ['add_members' => 'everyone'])->assertForbidden();

    $this->actingAs($owner)->postJson(route('messaging.conversations.settings', ['conversation' => $group]), ['add_members' => 'admins', 'pin_messages' => 'admins'])->assertOk();
    $this->actingAs($member)->postJson(route('messaging.conversations.members.add', ['conversation' => $group]), ['users' => [User::factory()->create()->id]])->assertForbidden();
    $this->actingAs($member)->postJson(route('messaging.messages.pin', ['message' => $message['id']]), ['pin' => false])->assertForbidden();

    $this->actingAs($owner)->postJson(route('messaging.conversations.info', ['conversation' => $group]), ['name' => '営業部（東日本）', 'description' => '東日本の案件'])->assertOk();
    $details = $this->actingAs($member)->getJson(route('messaging.conversations.details', ['conversation' => $group]))->json();
    expect($details['conversation']['name'])->toBe('営業部（東日本）')
        ->and($details['pins'][0]['id'])->toBe($message['id']);
});

it('hands 管理者 to the longest member when the last one leaves', function () {
    [$owner, $first, $second] = User::factory()->count(3)->create();
    $group = messagingGroup($owner, [$first, $second]);

    $this->actingAs($owner)->postJson(route('messaging.conversations.leave', ['conversation' => $group]))->assertOk();

    expect(Participant::query()->where('conversation_id', $group)->where('user_id', $first->id)->value('role'))->toBe(ParticipantRole::Admin)
        ->and(Participant::query()->where('conversation_id', $group)->where('user_id', $owner->id)->whereNotNull('left_at')->exists())->toBeTrue();
    $this->actingAs($owner)->getJson(route('messaging.conversations.show', ['conversation' => $group]))->assertNotFound();
});

it('keeps at least one 管理者', function () {
    [$owner, $member] = User::factory()->count(2)->create();
    $group = messagingGroup($owner, [$member]);

    $this->actingAs($owner)->postJson(route('messaging.conversations.members.role', ['conversation' => $group, 'user' => $owner->id]), ['role' => 'member'])->assertStatus(422);
    $this->actingAs($owner)->postJson(route('messaging.conversations.members.role', ['conversation' => $group, 'user' => $member->id]), ['role' => 'admin'])->assertOk();
    $this->actingAs($owner)->postJson(route('messaging.conversations.members.role', ['conversation' => $group, 'user' => $owner->id]), ['role' => 'member'])->assertOk();
});

it('notifies mentioned members in the bell, and @全員 only where allowed', function () {
    [$owner, $tanaka, $suzuki] = User::factory()->count(3)->create();
    $group = messagingGroup($owner, [$tanaka, $suzuki]);

    $message = messagingSay($owner, $group, "@{$tanaka->name} 確認お願いします", ['mentions' => [$tanaka->id, 999999]]);
    expect($message['mentions'])->toBe([$tanaka->id]);
    $notice = DatabaseNotification::query()->where('notifiable_id', $tanaka->id)->first();
    expect($notice)->not->toBeNull()
        ->and($notice->data['title'])->toContain('メンション')
        // the text of the message is not put into the notification
        ->and(json_encode($notice->data, JSON_UNESCAPED_UNICODE))->not->toContain('確認お願いします')
        ->and(DatabaseNotification::query()->where('notifiable_id', $suzuki->id)->exists())->toBeFalse()
        ->and($this->actingAs($tanaka)->getJson(route('messaging.inbox'))->json('conversations.0.mention'))->toBeTrue();

    // @全員: 管理者 only by default
    $this->actingAs($tanaka)->postJson(route('messaging.conversations.send', ['conversation' => $group]), ['body' => '@全員 聞いて', 'mention_all' => true])->assertStatus(422);
    messagingSay($owner, $group, '@全員 朝礼です', ['mention_all' => true]);
    expect(DatabaseNotification::query()->where('notifiable_id', $suzuki->id)->count())->toBe(1);
});

it('counts group read marks from the members\' watermarks', function () {
    [$owner, $a, $b] = User::factory()->count(3)->create();
    $group = messagingGroup($owner, [$a, $b]);
    $message = messagingSay($owner, $group, '既読をつけてね');

    $this->actingAs($a)->postJson(route('messaging.conversations.read', ['conversation' => $group]), ['message' => $message['id']])->assertOk();
    $reads = collect($this->actingAs($owner)->getJson(route('messaging.conversations.reads', ['conversation' => $group]))->json('reads'));

    expect($reads->where('user', '!=', $owner->id)->filter(fn (array $r): bool => $r['read'] >= $message['id'])->pluck('user')->all())->toBe([$a->id]);
});
