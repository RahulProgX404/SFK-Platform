<?php

/*
 * Round trips to the database that every request or every dialog paid (performance pass 2026-11-20,
 * docs/performance.md; each one ~80 ms from Japan to Neon): the cache versions, the user choices of 絞り込む and of
 * コメント's 宛先, and choices asked twice in one 絞り込む. Each change must still be seen on the next request.
 */

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Cache\CacheVersions;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Users\UserChoices;
use Modules\Core\Auth\CachedUserProvider;

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

    // a bump by another environment sharing the database (Render ⇄ local development): after STORE_SECONDS the next
    // request still answers from the store without waiting for the database, which is read after its response
    // (stale-while-revalidate, pass 2026-11-24); the request after that has it
    DB::table('cache_versions')->where('name', CacheVersions::REFERENCE)->update(['version' => 'elsewhere']);
    ($this->nextRequest)();
    expect($version())->toBe($bumped);
    $this->travel(CacheVersions::STORE_SECONDS + 1)->seconds();
    ($this->nextRequest)();
    $stale = null;
    expect(($this->queries)(function () use ($version, &$stale): void {
        $stale = $version();
    }, 'cache_versions'))->toBe(0)
        ->and($stale)->toBe($bumped);
    expect(($this->queries)(fn () => defer()->invoke(), 'cache_versions'))->toBe(1);
    ($this->nextRequest)();
    expect($version())->toBe('elsewhere');

    // much older than that (STALE_SECONDS): read before the request goes on
    DB::table('cache_versions')->where('name', CacheVersions::REFERENCE)->update(['version' => 'later']);
    $this->travel(CacheVersions::STALE_SECONDS + 1)->seconds();
    ($this->nextRequest)();
    expect($version())->toBe('later');
    // a revalidation that comes after a bump of this environment leaves the bump's versions
    ReferenceCache::bump();
    $own = $version();
    ($this->nextRequest)();
    expect($version())->toBe($own);
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

it('reads the signed-in user from the reference cache and sees every change of the user on the next request (2026-11-24)', function () {
    $user = User::factory()->create(['name' => '青木']);
    $provider = Auth::createUserProvider('users');
    expect($provider)->toBeInstanceOf(CachedUserProvider::class);
    $read = fn (): ?User => $provider->retrieveById($user->id);

    expect(($this->queries)($read, 'users'))->toBe(1);
    ($this->nextRequest)();
    $cached = null;
    expect(($this->queries)(function () use ($read, &$cached): void {
        $cached = $read();
    }, 'users'))->toBe(0)
        ->and($cached)->toBeInstanceOf(User::class)
        ->and($cached->is($user))->toBeTrue()
        ->and($cached->exists)->toBeTrue()
        ->and($cached->employment_status)->toBe($user->employment_status)
        ->and($cached->getAuthPassword())->toBe($user->getAuthPassword());

    // a saved user (name, password, 在籍状況, rights …) is read afresh on the next request
    $user->forceFill(['name' => '青木 一郎', 'show_financial_analysis' => true])->save();
    ($this->nextRequest)();
    expect($read()->name)->toBe('青木 一郎')
        ->and($read()->show_financial_analysis)->toBeTrue();
    $user->forceFill(['employment_status' => 'retired'])->save();
    ($this->nextRequest)();
    expect($read()->employment_status->value)->toBe('retired');
    // a deleted user is gone on the next request
    $user->delete();
    ($this->nextRequest)();
    expect($read())->toBeNull();

    // where the cache store is the database itself (Render): from the database, as before
    config(['cache.default' => 'database']);
    $other = User::factory()->create();
    ($this->nextRequest)();
    $provider->retrieveById($other->id);
    expect(($this->queries)(fn () => $provider->retrieveById($other->id), 'users'))->toBe(1);
});
