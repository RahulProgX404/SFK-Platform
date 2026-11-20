<?php

/*
 * Round trips to the database that every request or every dialog paid (performance pass 2026-11-20,
 * docs/performance.md; each one ~80 ms from Japan to Neon): the cache versions, the user choices of 絞り込む and of
 * コメント's 宛先, and choices asked twice in one 絞り込む. Each change must still be seen on the next request.
 */

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Cache\CacheVersions;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Users\UserChoices;

beforeEach(function () {
    config(['core.reference_cache' => true]);
    $this->nextRequest = fn () => app()->instance('request', Request::create('/erp'));
    // the queries one call sends to the database
    $this->queries = function (Closure $call, string $table): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $call();
        $count = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], '"'.$table.'"')));
        DB::disableQueryLog();

        return $count;
    };
    ($this->nextRequest)();
});

it('reads the cache versions from the cache store, sees a bump on the next request and another environment\'s soon after', function () {
    $version = fn (): string => CacheVersions::get(CacheVersions::REFERENCE);

    expect(($this->queries)($version, 'cache_versions'))->toBe(1);
    ($this->nextRequest)();
    expect(($this->queries)($version, 'cache_versions'))->toBe(0);

    // a bump: the bumping request reads its own version from the database, the next one gets it from the store
    $before = $version();
    ReferenceCache::bump();
    $bumped = $version();
    expect($bumped)->not->toBe($before);
    ($this->nextRequest)();
    $next = null;
    expect(($this->queries)(function () use ($version, &$next): void {
        $next = $version();
    }, 'cache_versions'))->toBe(0)
        ->and($next)->toBe($bumped);

    // a bump by another environment sharing the database (Render ⇄ local development): seen after STORE_SECONDS
    DB::table('cache_versions')->where('name', CacheVersions::REFERENCE)->update(['version' => 'elsewhere']);
    ($this->nextRequest)();
    expect($version())->toBe($bumped);
    $this->travel(CacheVersions::STORE_SECONDS + 1)->seconds();
    ($this->nextRequest)();
    expect($version())->toBe('elsewhere');
});

it('reads the cache versions from the database where the cache store is the database itself', function () {
    config(['cache.default' => 'database']);
    $version = fn (): string => CacheVersions::get(CacheVersions::REFERENCE);

    expect(($this->queries)($version, 'cache_versions'))->toBe(1);
    ($this->nextRequest)();
    expect(($this->queries)($version, 'cache_versions'))->toBe(1);
});

it('keeps the user choices in the reference cache and sees a new or renamed user on the next request', function () {
    $first = User::factory()->create(['name' => '青木']);
    ($this->nextRequest)();

    expect(UserChoices::all())->toHaveKey($first->id)
        ->and(UserChoices::active())->toHaveKey($first->id);
    ($this->nextRequest)();
    expect(($this->queries)(fn () => [UserChoices::all(), UserChoices::active()], 'users'))->toBe(0);

    $second = User::factory()->create(['name' => '井上']);
    $first->update(['name' => '青木 一郎']);
    ($this->nextRequest)();
    expect(UserChoices::all())->toMatchArray([$first->id => '青木 一郎', $second->id => '井上']);

    // who can no longer sign in is no 宛先, but stays a 絞り込む choice (their records keep them)
    $second->forceFill(['employment_status' => 'retired'])->save();
    ($this->nextRequest)();
    expect(UserChoices::active())->not->toHaveKey($second->id)
        ->and(UserChoices::all())->toHaveKey($second->id);
});

it('asks a choice list shared by several 絞り込む fields once per request', function () {
    $asked = 0;
    $boxes = function () use (&$asked): array {
        $asked++;

        return ['1' => '段ボールA'];
    };
    $fields = [FilterField::choice('cardboard_id', '段ボール', $boxes), FilterField::choice('partition_id', '仕切り', $boxes)];

    foreach ($fields as $field) {
        expect($field->toDialog()['options'])->toContain(['value' => '1', 'label' => '段ボールA']);
    }
    expect($asked)->toBe(1);

    ($this->nextRequest)();
    $fields[0]->getOptions();
    expect($asked)->toBe(2);
});
