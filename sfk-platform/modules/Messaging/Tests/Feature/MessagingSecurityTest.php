<?php

/*
 * 社内メッセージ — security (docs/messaging.md "Security"): every conversation, message, file and real-time address is
 * the members' only. Changing an id in the address gives 404 (no probing), removed or departed members lose the
 * conversation and its files, unsent files are no one's, uploads are checked by content and never public.
 */

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Messaging\Application\Signals;
use Modules\Messaging\Http\MessengerPulse;
use Modules\Messaging\Models\MessageAttachment;

beforeEach(function () {
    Storage::fake(config('filesystems.uploads', 'local'));
});

function messagingConversation(User $a, User $b): int
{
    return (int) test()->actingAs($a)->postJson(route('messaging.direct'), ['user' => $b->id])->json('id');
}

function messagingPost(User $from, int $conversation, array $payload): TestResponse
{
    return test()->actingAs($from)->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['client_id' => (string) Str::uuid(), ...$payload]);
}

it('answers 404 to anyone outside a conversation, for every read and write', function () {
    [$a, $b, $outsider] = User::factory()->count(3)->create();
    $conversation = messagingConversation($a, $b);
    $message = messagingPost($a, $conversation, ['body' => '内緒の話'])->json('message.id');

    $this->actingAs($outsider);
    $this->getJson(route('messaging.conversations.show', ['conversation' => $conversation]))->assertNotFound();
    $this->getJson(route('messaging.conversations.history', ['conversation' => $conversation]))->assertNotFound();
    $this->getJson(route('messaging.conversations.sync', ['conversation' => $conversation, 'after' => 0]))->assertNotFound();
    $this->getJson(route('messaging.conversations.reads', ['conversation' => $conversation]))->assertNotFound();
    $this->getJson(route('messaging.conversations.details', ['conversation' => $conversation]))->assertNotFound();
    $this->getJson(route('messaging.conversations.shared', ['conversation' => $conversation]))->assertNotFound();
    $this->postJson(route('messaging.conversations.send', ['conversation' => $conversation]), ['body' => '割り込み'])->assertNotFound();
    $this->postJson(route('messaging.conversations.read', ['conversation' => $conversation]), ['message' => $message])->assertNotFound();
    $this->postJson(route('messaging.conversations.state', ['conversation' => $conversation]), ['state' => 'pinned', 'value' => true])->assertNotFound();
    $this->patchJson(route('messaging.messages.edit', ['message' => $message]), ['body' => 'x'])->assertNotFound();
    $this->postJson(route('messaging.messages.unsend', ['message' => $message]))->assertNotFound();
    $this->postJson(route('messaging.messages.hide', ['message' => $message]))->assertNotFound();
    $this->postJson(route('messaging.messages.react', ['message' => $message]), ['emoji' => '👍'])->assertNotFound();
    $this->postJson(route('messaging.messages.pin', ['message' => $message]))->assertNotFound();
    $this->postJson(route('messaging.messages.forward', ['message' => $message]), ['conversations' => [$conversation]])->assertNotFound();
    $this->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertNotFound();

    expect($this->getJson(route('messaging.inbox'))->json('conversations'))->toBe([])
        ->and($this->getJson(route('messaging.search', ['q' => '内緒']))->json('results'))->toBe([]);
});

it('requires a signed-in user for the API and the files', function () {
    $this->getJson(route('messaging.inbox'))->assertUnauthorized();
    $this->get(route('messaging.attachments.show', ['attachment' => 1]))->assertRedirect();
});

it('stores uploads privately, checks their content and serves them only to the members', function () {
    [$a, $b, $outsider] = User::factory()->count(3)->create();
    $conversation = messagingConversation($a, $b);

    // a script renamed to .pdf, an unknown type: refused
    $this->actingAs($a)->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->createWithContent('invoice.pdf', '<?php echo 1;')])->assertStatus(422);
    $this->actingAs($a)->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->create('tool.exe', 5)])->assertStatus(422);

    $upload = $this->actingAs($a)->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->image('現場写真.jpg', 640, 480)])->assertCreated()->json('attachment');
    expect($upload)->toMatchArray(['kind' => 'image', 'name' => '現場写真.jpg', 'w' => 640, 'h' => 480]);
    $stored = MessageAttachment::query()->find($upload['id']);
    expect($stored->path)->not->toContain('現場写真')->and($stored->path)->toStartWith('messaging/'.$conversation.'/');

    // not sent yet: only the uploader; another user cannot attach it
    $this->actingAs($b)->get(route('messaging.attachments.show', ['attachment' => $upload['id']]))->assertNotFound();
    messagingPost($b, $conversation, ['body' => '盗む', 'attachment_ids' => [$upload['id']]])->assertStatus(422);

    $message = messagingPost($a, $conversation, ['body' => '写真です', 'attachment_ids' => [$upload['id']]])->assertCreated()->json('message');
    expect($message['type'])->toBe('image')->and($message['att'][0]['id'])->toBe($upload['id']);

    $download = $this->actingAs($b)->get(route('messaging.attachments.show', ['attachment' => $upload['id']]));
    $download->assertOk();
    expect($download->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($download->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($download->headers->get('Content-Type'))->toBe('image/jpeg');
    $this->actingAs($outsider)->get(route('messaging.attachments.show', ['attachment' => $upload['id']]))->assertNotFound();

    // unsent: nobody reads the file any more
    $this->actingAs($a)->postJson(route('messaging.messages.unsend', ['message' => $message['id']]))->assertOk();
    $this->actingAs($b)->get(route('messaging.attachments.show', ['attachment' => $upload['id']]))->assertNotFound();
});

it('downloads documents as files, never shown inline', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingConversation($a, $b);
    $upload = $this->actingAs($a)->postJson(route('messaging.conversations.upload', ['conversation' => $conversation]), ['file' => UploadedFile::fake()->createWithContent('売上.csv', "日付,金額\n2026-11-01,1000\n")])->assertCreated()->json('attachment');
    messagingPost($a, $conversation, ['attachment_ids' => [$upload['id']]])->assertCreated();

    $response = $this->actingAs($b)->get(route('messaging.attachments.show', ['attachment' => $upload['id']]));
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Disposition'))->toContain(rawurlencode('売上.csv'));
});

it('answers the real-time pulse from a signed address only, without session or database', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingConversation($a, $b);
    $url = MessengerPulse::url($a->id, $conversation, $b->id);

    $this->get(route('messaging.pulse', ['user' => $a->id, 'conversation' => $conversation, 'peer' => $b->id, 'key' => MessengerPulse::signature($b->id, $conversation, $b->id)]))->assertNotFound();
    $this->get(route('messaging.pulse', ['user' => $a->id, 'conversation' => $conversation + 1, 'peer' => $b->id, 'key' => MessengerPulse::signature($a->id, $conversation, $b->id)]))->assertNotFound();

    // the other person types: seen through the pulse, by user id, for a few seconds
    $this->post(MessengerPulse::url($b->id, $conversation, $a->id), ['typing' => true])->assertOk();
    Signals::seen($b->id);

    DB::enableQueryLog();
    $pulse = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([])
        ->and($pulse['t'])->toBe([(string) $b->id])
        ->and($pulse['o']['online'])->toBeTrue();

    $this->travel(10)->seconds();
    expect($this->get($url)->json('t'))->toBe([]);
});

it('changes the pulse versions after a message and a read', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingConversation($a, $b);
    $url = MessengerPulse::url($b->id, $conversation, $a->id);
    $before = $this->get($url)->json();

    $message = messagingPost($a, $conversation, ['body' => 'やあ'])->json('message.id');
    $after = $this->get($url)->json();
    expect($after['c'])->not->toBe($before['c'])->and($after['i'])->not->toBe($before['i']);

    $this->actingAs($b)->postJson(route('messaging.conversations.read', ['conversation' => $conversation]), ['message' => $message]);
    expect($this->get($url)->json('r'))->not->toBe($after['r']);
});

it('hides presence of a user who switched it off', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingConversation($a, $b);
    Signals::seen($b->id);
    $this->actingAs($b)->postJson(route('messaging.preferences'), ['presence' => false])->assertOk()->assertJsonPath('prefs.presence', false);

    expect($this->get(MessengerPulse::url($a->id, $conversation, $b->id))->json('o'))->toBe(['state' => 'offline', 'online' => false, 'seen' => null]);
});

it('limits how fast one user can send', function () {
    config(['messaging.limits.send' => 3]);
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = messagingConversation($a, $b);

    foreach (range(1, 3) as $i) {
        messagingPost($a, $conversation, ['body' => "連投{$i}"])->assertCreated();
    }
    messagingPost($a, $conversation, ['body' => '多すぎ'])->assertStatus(429);
});
