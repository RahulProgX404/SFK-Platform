<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Every test (application tests/ and module modules/<Name>/Tests/) boots the Laravel app.
| Feature tests also get a fresh database per test.
*/

pest()->extend(TestCase::class)->in('Unit', '../modules/*/Tests/Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/Tests/Feature');
