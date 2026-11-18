<?php

/*
 * 社内メッセージ — direct conversations (docs/messaging.md): one conversation per pair, sending with the sender's own id
 * against double sending, unread counts and read / delivered watermarks, edits and 送信取消 within the company's
 * windows with an audit, reactions (one per person), 自分のみ削除 and 「トークを削除」 (one's own copy only).
 */

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageEdit;
use Modules\Messaging\Models\MessageReaction;
use Modules\Messaging\Models\Participant;

function messagingDirect(User $from, User $to): int
{
    return (int) test()->actingAs($from)->postJson(route('messaging.direct'), ['user' => $to->id])->assertOk()->json('id');
}

/** @param  array<string, mixed>  $extra */
function messagingSend(User $from, int $conversation, string $body, array $extra = []): array
{
    return test()->actingAs($from)
        ->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => $body, 'client_id' => (string) Str::uuid(), ...$extra])
        ->assertCreated()
        ->json('message');
}

it('opens one direct conversation per pair, whoever starts it', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $first = messagingDirect($a, $b);
    $second = messagingDirect($b, $a);

    expect($first)->toBe($second)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Participant::query()->where('conversation_id', $first)->count())->toBe(2);
});

it('refuses a conversation with oneself or with someone who left the company', function () {
    $a = User::factory()->create();
    $gone = User::factory()->create(['employment_status' => 'resigned']);

    $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $a->id])->assertStatus(422);
    $this->actingAs($a)->postJson(route('messaging.direct'), ['user' => $gone->id])->assertStatus(422);
});

it('sends Japanese, Nepali and emoji text unchanged and stores a resent request only once', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $text = "お疲れ様です。\nनमस्ते 🙏 <b>bold</b>";
    $clientId = (string) Str::uuid();

    $first = $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => $text, 'client_id' => $clientId])->assertCreated()->json('message');
    $again = $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => $text, 'client_id' => $clientId])->assertCreated()->json('message');

    expect($first['id'])->toBe($again['id'])
        ->and($first['body'])->toBe($text)
        ->and(Message::query()->count())->toBe(1)
        ->and(Conversation::query()->find($conversation)->last_message_id)->toBe($first['id']);
});

it('counts unread messages from the read watermark and marks them read and delivered', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    messagingSend($a, $conversation, '一通目');
    $last = messagingSend($a, $conversation, '二通目');

    $inbox = $this->actingAs($b)->getJson(route('messaging.inbox'))->assertOk()->json('conversations');
    expect($inbox[0]['unread'])->toBe(2)
        ->and($inbox[0]['name'])->toBe($a->name)
        ->and($inbox[0]['last']['text'])->toBe('二通目');
    $this->actingAs($b)->getJson(route('messaging.unread'))->assertOk()->assertJsonPath('count', 2);

    // opening the conversation delivers, reading marks read
    $this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->assertOk();
    $theirs = Participant::query()->where('conversation_id', $conversation)->where('user_id', $b->id)->first();
    expect($theirs->last_delivered_message_id)->toBe($last['id']);

    $this->actingAs($b)->postJson(route('messaging.conversations.read', ['conversation' => $conversation]), ['message' => $last['id'] + 100])->assertOk();
    expect($theirs->fresh()->last_read_message_id)->toBe($last['id']);

    $reads = $this->actingAs($a)->getJson(route('messaging.conversations.reads', ['conversation' => $conversation]))->json('reads');
    expect(collect($reads)->firstWhere('user', $b->id)['read'])->toBe($last['id'])
        ->and($this->actingAs($b)->getJson(route('messaging.inbox'))->json('conversations.0.unread'))->toBe(0);
});

it('pages the history by keyset and syncs new and changed messages', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $ids = [];
    for ($i = 1; $i <= 45; $i++) {
        $ids[] = messagingSend($a, $conversation, "メッセージ{$i}")['id'];
    }

    $open = $this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->assertOk();
    expect($open->json('messages'))->toHaveCount(40)
        ->and($open->json('more'))->toBeTrue()
        ->and($open->json('messages.0.body'))->toBe('メッセージ6');

    $older = $this->actingAs($b)->getJson(route('messaging.conversations.history', ['conversation' => $conversation, 'before' => $open->json('messages.0.id')]))->json();
    expect($older['messages'])->toHaveCount(5)->and($older['more'])->toBeFalse();

    $around = $this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation, 'around' => $ids[2]]))->json();
    expect(collect($around['messages'])->pluck('id'))->toContain($ids[2]);

    $since = now()->toIso8601String();
    $this->travel(2)->seconds();
    $new = messagingSend($a, $conversation, '新着');
    $this->actingAs($a)->patchJson(route('messaging.messages.edit', ['message' => $ids[44]]), ['body' => '直しました'])->assertOk();

    $sync = $this->actingAs($b)->getJson(route('messaging.conversations.sync', ['conversation' => $conversation, 'after' => $ids[44], 'from' => $ids[5], 'since' => $since]))->assertOk()->json();
    expect(collect($sync['messages'])->pluck('id')->all())->toBe([$new['id']])
        ->and(collect($sync['changed'])->firstWhere('id', $ids[44])['body'])->toBe('直しました');
});

it('lets the sender edit and unsend within the windows, keeping the old text for the audit', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $message = messagingSend($a, $conversation, '元の文');

    $this->actingAs($b)->patchJson(route('messaging.messages.edit', ['message' => $message['id']]), ['body' => '他人が編集'])->assertForbidden();
    $edited = $this->actingAs($a)->patchJson(route('messaging.messages.edit', ['message' => $message['id']]), ['body' => '直した文'])->assertOk()->json('message');
    expect($edited['body'])->toBe('直した文')->and($edited['edited'])->toBeTrue();

    $this->actingAs($b)->postJson(route('messaging.messages.unsend', ['message' => $message['id']]))->assertForbidden();
    $unsent = $this->actingAs($a)->postJson(route('messaging.messages.unsend', ['message' => $message['id']]))->assertOk()->json('message');
    expect($unsent['unsent'])->toBeTrue()->and($unsent['body'])->toBeNull();

    // the other person sees only that it was unsent; the audit keeps both texts
    $seen = $this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->json('messages.0');
    expect($seen['body'])->toBeNull()->and($seen['unsent'])->toBeTrue()
        ->and(MessageEdit::query()->orderBy('id')->pluck('body')->all())->toBe(['元の文', '直した文'])
        ->and(MessageEdit::query()->orderBy('id')->pluck('action')->all())->toBe(['edit', 'unsend']);

    // past the window
    $late = messagingSend($a, $conversation, '昨日の文');
    $this->travel((int) config('messaging.edit_window_minutes') + 1)->minutes();
    $this->actingAs($a)->patchJson(route('messaging.messages.edit', ['message' => $late['id']]), ['body' => '遅い'])->assertForbidden();
    $this->actingAs($a)->postJson(route('messaging.messages.unsend', ['message' => $late['id']]))->assertForbidden();
});

it('keeps one reaction per person: the same again takes it back, another replaces it', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $message = messagingSend($a, $conversation, 'リアクションしてね');
    $react = fn (string $emoji) => $this->actingAs($b)->postJson(route('messaging.messages.react', ['message' => $message['id']]), ['emoji' => $emoji])->assertOk()->json('message.rx');

    expect($react('👍'))->toBe([['e' => '👍', 'n' => 1, 'mine' => true, 'users' => [$b->id]]])
        ->and(collect($react('❤️'))->pluck('e')->all())->toBe(['❤️'])
        ->and($react('❤️'))->toBe([])
        ->and(MessageReaction::query()->count())->toBe(0);

    $this->actingAs($b)->postJson(route('messaging.messages.react', ['message' => $message['id']]), ['emoji' => '<script>'])->assertStatus(422);
    $this->actingAs($b)->postJson(route('messaging.messages.react', ['message' => $message['id']]), ['emoji' => 'abc'])->assertStatus(422);
});

it('hides a message or the whole history for one member only; the conversation comes back with a new message', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $first = messagingSend($a, $conversation, '消したい');
    messagingSend($a, $conversation, '残す');

    $this->actingAs($b)->postJson(route('messaging.messages.hide', ['message' => $first['id']]))->assertOk();
    expect(collect($this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->json('messages'))->pluck('body')->all())->toBe(['残す'])
        ->and(collect($this->actingAs($a)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->json('messages'))->pluck('body')->all())->toBe(['消したい', '残す']);

    $this->actingAs($b)->postJson(route('messaging.conversations.state', ['conversation' => $conversation]), ['state' => 'hidden', 'value' => true])->assertOk();
    expect($this->actingAs($b)->getJson(route('messaging.inbox'))->json('conversations'))->toBe([]);

    messagingSend($a, $conversation, '新しい話');
    $this->actingAs($b)->getJson(route('messaging.inbox'))->assertJsonPath('conversations.0.last.text', '新しい話');
    expect(collect($this->actingAs($b)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->json('messages'))->pluck('body')->all())->toBe(['新しい話'])
        ->and(collect($this->actingAs($a)->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->json('messages')))->toHaveCount(3);
});

it('keeps pin, archive, favourite and mute as each member\'s own state', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    messagingSend($a, $conversation, 'こんにちは');

    foreach (['pinned', 'archived', 'favorite', 'muted'] as $state) {
        $this->actingAs($b)->postJson(route('messaging.conversations.state', ['conversation' => $conversation]), ['state' => $state, 'value' => true])->assertOk();
    }

    $theirs = $this->actingAs($b)->getJson(route('messaging.inbox'))->json('conversations.0');
    $mine = $this->actingAs($a)->getJson(route('messaging.inbox'))->json('conversations.0');
    expect([$theirs['pinned'], $theirs['archived'], $theirs['favorite'], $theirs['muted']])->toBe([true, true, true, true])
        ->and([$mine['pinned'], $mine['archived'], $mine['favorite'], $mine['muted']])->toBe([false, false, false, false])
        // a muted conversation is left out of the badge
        ->and($this->actingAs($b)->getJson(route('messaging.unread'))->json('count'))->toBe(0);
});

it('forwards a message into the user\'s other conversations with who wrote it first', function () {
    [$a, $b, $c] = User::factory()->count(3)->create();
    $ab = messagingDirect($a, $b);
    $bc = messagingDirect($b, $c);
    $original = messagingSend($a, $ab, '共有してください');

    $this->actingAs($b)->postJson(route('messaging.messages.forward', ['message' => $original['id']]), ['conversations' => [$bc]])->assertOk()->assertJsonPath('sent', 1);
    $forwarded = $this->actingAs($c)->getJson(route('messaging.conversations.show', ['conversation' => $bc]))->json('messages.0');
    expect($forwarded['body'])->toBe('共有してください')
        ->and($forwarded['user'])->toBe($b->id)
        ->and($forwarded['fwd'])->toBe(['user' => $a->id, 'name' => $a->name]);

    // not into a conversation the user is not in
    $this->actingAs($c)->postJson(route('messaging.messages.forward', ['message' => $forwarded['id']]), ['conversations' => [$ab]])->assertNotFound();
});

it('replies with a quote of the message replied to', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);
    $question = messagingSend($a, $conversation, '明日の会議は何時ですか？');

    $answer = messagingSend($b, $conversation, '10時です', ['reply_to_id' => $question['id']]);
    expect($answer['reply'])->toMatchArray(['id' => $question['id'], 'user' => $a->id, 'text' => '明日の会議は何時ですか？']);
});

it('refuses empty and too long messages', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingDirect($a, $b);

    $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => "  \n "])->assertStatus(422);
    $this->actingAs($a)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => str_repeat('あ', 5001)])->assertStatus(422);
});
