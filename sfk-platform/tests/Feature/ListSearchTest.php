<?php

/*
 * 検索 of the app lists: each app keeps its searchable columns, every comparison folds 全角 / 半角, case and
 * カタカナ / ひらがな together and also reads romaji as kana (SearchQuery), each word must be found, and the icon right
 * of the box searches one column of the 一覧 instead (text, number, date, choice and user fields of 絞り込む). On
 * PostgreSQL every column searched by default has a trigram index on the compared form (SearchIndex).
 */

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Search\SearchIndex;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\AppResource;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Filament\Resources\Partners\Pages\ListPartners;
use Modules\MasterData\Models\Partner;

function searchPartner(string $name, string $kana, array $attributes = []): Partner
{
    return Partner::query()->create([
        'name' => $name, 'name_kana' => $kana, 'short_name' => mb_substr($name, 0, 4), 'is_customer' => true,
        'postal_code' => '100-0001', 'address' => '東京都', 'phone' => '03-0000-0000',
        'invoice_recipient' => '得意先名', 'delivery_note_recipient' => '得意先名', ...$attributes,
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->shiraishi = searchPartner('シライシ封筒', 'しらいしふうとう');
    $this->abc = searchPartner('ＡＢＣ商事', 'えーびーしーしょうじ');
    $this->yamada = searchPartner('ﾔﾏﾀﾞ印刷', 'やまだいんさつ');
});

it('searches every app on the compared form only, each searched column with its trigram index on PostgreSQL', function () {
    $searched = [];
    foreach (Filament::getPanels() as $panel) {
        Filament::setCurrentPanel($panel->getId());
        foreach ($panel->getResources() as $resource) {
            $page = is_subclass_of($resource, AppResource::class) && $resource::hasPage('index') ? $resource::getPages()['index']->getPage() : null;
            if ($page === null || ! is_subclass_of($page, AppListRecords::class)) {
                continue;
            }

            $sql = Livewire::test($page, ['listView' => 'all'])->set('tableSearch', 'qzx')->instance()->getFilteredTableQuery()->toRawSql();
            preg_match_all('/sfk_search_norm\(CAST\("(\w+)"\."(\w+)" AS TEXT\)\)/', $sql, $hits, PREG_SET_ORDER);
            foreach ($hits as $hit) {
                $searched[$hit[1].'.'.$hit[2]] = [$hit[1], $hit[2]];
            }

            // no comparison left on the stored text as it is
            expect(preg_replace('/sfk_search_norm\(CAST\([^)]*\)\) LIKE \'[^\']*\' ESCAPE \'!\'/', '', $sql))->not->toContain('qzx');
        }
    }

    expect($searched)->toHaveKeys(['sales.product_name', 'partners.name', 'orders.order_number', 'users.name']);

    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    $indexes = DB::table('pg_indexes')->pluck('indexname')->all();
    $missing = array_keys(array_filter($searched, fn (array $column): bool => ! in_array(SearchIndex::name(...$column), $indexes, true)));
    expect($missing)->toBe([]);

    // the search reads the index, not the table
    DB::statement('SET enable_seqscan = off');
    $plan = collect(DB::select('EXPLAIN '.Partner::query()->where(fn ($query) => SearchQuery::where($query, 'partners.name', 'しらいし'))->toRawSql()))
        ->map(fn (object $row): string => (string) $row->{'QUERY PLAN'})->implode("\n");
    DB::statement('RESET enable_seqscan');
    expect($plan)->toContain(SearchIndex::name('partners', 'name'));
});

it('finds records whatever the width, case, kana or romaji of the word', function (string $search, array $found) {
    $partners = ['shiraishi' => $this->shiraishi, 'abc' => $this->abc, 'yamada' => $this->yamada];

    Livewire::test(ListPartners::class)
        ->set('tableSearch', $search)
        ->assertCanSeeTableRecords(array_map(fn (string $key): Partner => $partners[$key], $found))
        ->assertCanNotSeeTableRecords(array_values(array_diff_key($partners, array_flip($found))));
})->with([
    'hiragana for katakana' => ['しらいし', ['shiraishi']],
    'katakana for hiragana kana field' => ['ヤマダ', ['yamada']],
    'half-width katakana' => ['ｼﾗｲｼ', ['shiraishi']],
    'romaji' => ['shiraishi', ['shiraishi']],
    'romaji (Kunrei)' => ['yamada', ['yamada']],
    'latin for full-width, any case' => ['abc', ['abc']],
    'full-width for latin' => ['ＡＢＣ', ['abc']],
    'kanji' => ['封筒', ['shiraishi']],
    'every word must be found' => ['印刷　やまだ', ['yamada']],
    'words found in different records only' => ['封筒 印刷', []],
]);

it('searches one column chosen with the icon right of the search box', function () {
    $this->yamada->update(['partner_group' => PartnerGroup::cases()[0]->value]);
    $this->abc->update(['bill_threshold_man' => 120]);
    $this->shiraishi->forceFill(['created_at' => '2026-10-05 09:30:00'])->save();
    $this->shiraishi->staff()->attach($staff = User::factory()->create(['name' => '菊池 毅']));
    // しらいし in another partner's name only
    $other = searchPartner('白石商店 シライシ', 'はくせきしょうてん');

    $list = Livewire::test(ListPartners::class, ['listView' => 'all']);
    expect($list->instance()->searchColumnOptions())->toHaveKeys(['name', 'name_kana', 'partner_group', 'bill_threshold_man', 'created_at', 'staff'])
        ->and($list->instance()->activeSearchColumn())->toBeNull();
    // the icon (a render hook of the panel: on a real page)
    $this->get('/erp/partners')->assertOk()->assertSee('sfk-search-column-btn', false)->assertSee('標準の検索項目');

    // ふりがな only: the partner named シライシ without that reading is not found
    $list->call('setSearchColumn', 'name_kana')
        ->assertSet('searchColumn', 'name_kana')
        ->set('tableSearch', 'shiraishi')
        ->assertCanSeeTableRecords([$this->shiraishi])
        ->assertCanNotSeeTableRecords([$other]);

    // a choice by the label of its option, a number, a date as typed, a user by name
    $list->call('setSearchColumn', 'partner_group')->set('tableSearch', PartnerGroup::cases()[0]->label())
        ->assertCanSeeTableRecords([$this->yamada])->assertCanNotSeeTableRecords([$this->abc, $this->shiraishi]);
    $list->call('setSearchColumn', 'bill_threshold_man')->set('tableSearch', '１２０')
        ->assertCanSeeTableRecords([$this->abc])->assertCanNotSeeTableRecords([$this->yamada]);
    $list->call('setSearchColumn', 'created_at')->set('tableSearch', '2026/10/5')
        ->assertCanSeeTableRecords([$this->shiraishi])->assertCanNotSeeTableRecords([$this->abc]);
    $list->call('setSearchColumn', 'staff')->set('tableSearch', '菊池')
        ->assertCanSeeTableRecords([$this->shiraishi])->assertCanNotSeeTableRecords([$this->abc]);

    // back to the app's searchable columns; an unknown column is not taken
    $list->call('setSearchColumn', null)->set('tableSearch', 'shiraishi')->assertCanSeeTableRecords([$this->shiraishi, $other]);
    $list->call('setSearchColumn', 'password')->assertSet('searchColumn', null);
});

it('keeps the chosen search column for each user until they log out; every login starts from the default', function () {
    $first = auth()->user();
    $second = User::factory()->superAdmin()->create();

    Livewire::test(ListPartners::class)->call('setSearchColumn', 'name');
    expect(Livewire::test(ListPartners::class)->instance()->activeSearchColumn())->toBe('name');

    // another user in the same browser: the default
    $this->actingAs($second);
    expect(Livewire::test(ListPartners::class)->instance()->activeSearchColumn())->toBeNull();
    $this->actingAs($first);
    expect(Livewire::test(ListPartners::class)->instance()->activeSearchColumn())->toBe('name');

    // log out, log in again: the default
    Auth::logout();
    $this->actingAs($first);
    expect(Livewire::test(ListPartners::class)->instance()->activeSearchColumn())->toBeNull();

    Livewire::test(ListPartners::class)->call('setSearchColumn', 'name_kana');
    Auth::login($first);
    expect(Livewire::test(ListPartners::class)->instance()->activeSearchColumn())->toBeNull();

    // the browser cannot set it past the list of columns
    expect(fn () => Livewire::test(ListPartners::class)->set('searchColumn', 'password'))->toThrow(Exception::class);
});
