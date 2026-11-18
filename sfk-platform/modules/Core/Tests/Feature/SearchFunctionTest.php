<?php

/*
 * sfk_search_norm on SQLite (SearchQuery::registerFunction): registered when a connection really opens, never by opening
 * one at boot — a connection made but not used (a database cache store resolved by a rate limiter) opened the database
 * during `docker build` (composer → package:discover), where there is none, and the build failed (2026-11-18).
 */

use Illuminate\Support\Facades\DB;

it('does not open a SQLite connection just to register the search function', function () {
    config(['database.connections.sfk_absent' => ['driver' => 'sqlite', 'database' => '/nonexistent/sfk-absent.sqlite', 'prefix' => '']]);

    // making the connection (ConnectionEstablished → registerFunction) must not touch the missing file
    $connection = DB::connection('sfk_absent');

    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);
    DB::purge('sfk_absent');
});

it('has the search function on a SQLite connection opened later', function () {
    config(['database.connections.sfk_lazy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    $connection = DB::connection('sfk_lazy');

    expect($connection->selectOne("select sfk_search_norm('ＡＢＣ') as folded")->folded)->toBe('abc');
    DB::purge('sfk_lazy');
});
