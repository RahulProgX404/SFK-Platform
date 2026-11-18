<?php

/*
 * ERP records shared in 社内メッセージ (Core RecordCards, docs/messaging.md "ERP records"): a structured card drawn for
 * each reader from the record as it is now — only for a reader who may open the record, each field under their
 * フィールドのアクセス権; a deleted record says so. Only records the sender may open can be shared; the picker searches
 * the sender's own visible records.
 */

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Models\BusinessApp;
use Modules\MasterData\Models\Partner;
use Modules\Messaging\Models\Message;

function shareCustomer(string $name = '株式会社アルファ'): Partner
{
    return Partner::query()->create(['name' => $name, 'short_name' => mb_substr($name, 0, 6), 'is_customer' => true, 'code' => 'C'.random_int(1000, 9999)]);
}

it('draws a shared record as a card for readers who may open it, and only says it exists to the others', function () {
    [$sender, $colleague, $outsider] = User::factory()->count(3)->create();
    openAppTo('partner-master', $sender, $colleague);
    $partner = shareCustomer();
    $group = (int) $this->actingAs($sender)->postJson(route('messaging.groups.store'), ['name' => '営業', 'members' => [$colleague->id, $outsider->id]])->json('id');

    $apps = collect($this->actingAs($sender)->getJson(route('messaging.records.apps'))->json('apps'))->pluck('code');
    expect($apps)->toContain('partner-master');
    $found = $this->actingAs($sender)->getJson(route('messaging.records.search', ['app' => 'partner-master', 'q' => 'アルファ']))->json('records');
    expect($found[0])->toMatchArray(['id' => (string) $partner->id, 'title' => '株式会社アルファ']);

    $sent = $this->actingAs($sender)->postJson(route('messaging.conversations.send', ['conversation' => $group]), [
        'client_id' => (string) Str::uuid(), 'record' => ['app' => 'partner-master', 'id' => (string) $partner->id],
    ])->assertCreated()->json('message');
    expect($sent['record'])->toMatchArray(['state' => 'ok', 'title' => '株式会社アルファ'])
        ->and(collect($sent['record']['fields'])->pluck('label')->all())->toContain('名称')
        // nothing of the record is stored with the message: only the reference
        ->and(Message::query()->find($sent['id'])->meta)->toBe(['record' => ['app' => 'partner-master', 'id' => (string) $partner->id]]);

    $seen = fn (User $reader) => collect($this->actingAs($reader)->getJson(route('messaging.conversations.show', ['conversation' => $group]))->json('messages'))->firstWhere('id', $sent['id'])['record'];
    expect($seen($colleague)['state'])->toBe('ok')
        ->and($seen($outsider))->toBe(['state' => 'denied', 'app' => $seen($outsider)['app'], 'code' => 'partner-master', 'id' => (string) $partner->id])
        ->and(json_encode($seen($outsider), JSON_UNESCAPED_UNICODE))->not->toContain('アルファ');

    // a field the reader may not see is left out
    BusinessApp::query()->where('code', 'partner-master')->sole()->fieldPermissions()->create([
        'field' => 'short_name', 'sort_order' => 1, 'subject_type' => AccessSubject::Everyone, 'access' => FieldAccessLevel::None,
    ]);
    BusinessApp::forgetCached();
    expect(collect($seen($colleague)['fields'])->pluck('label')->all())->not->toContain('略称');

    // deleted: says so, no data
    $partner->delete();
    expect($seen($colleague))->toMatchArray(['state' => 'missing']);
});

it('refuses to share a record the sender may not open, and finds none for them', function () {
    [$sender, $other] = User::factory()->count(2)->create();
    $partner = shareCustomer('株式会社ベータ');
    $direct = (int) $this->actingAs($sender)->postJson(route('messaging.direct'), ['user' => $other->id])->json('id');

    expect($this->actingAs($sender)->getJson(route('messaging.records.search', ['app' => 'partner-master', 'q' => 'ベータ']))->json('records'))->toBe([]);
    $this->actingAs($sender)->postJson(route('messaging.conversations.send', ['conversation' => $direct]), [
        'client_id' => (string) Str::uuid(), 'record' => ['app' => 'partner-master', 'id' => (string) $partner->id],
    ])->assertStatus(422);
});
