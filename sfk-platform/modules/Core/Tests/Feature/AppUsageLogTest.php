<?php

/*
 * AppUsageLog::touch() (よく使うアプリ, 最近表示した項目): one statement per app screen — the first opening inserts, the next
 * ones add 1 and keep the last record opened (2026-11-11, fewer round trips to the database).
 */

use App\Models\User;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Models\AppUsage;

it('counts the openings of an app and keeps the record opened last', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $log = app(AppUsageLog::class);

    $log->touch($user, 'order-entry');
    $log->touch($user, 'order-entry', $other);
    $log->touch($user, 'order-entry');
    $log->touch($user, 'sales-entry');
    $log->touch($other, 'order-entry');

    $usage = AppUsage::query()->where('user_id', $user->id)->where('app_code', 'order-entry')->sole();
    expect($usage->visits)->toBe(3)
        ->and($usage->last_visited_at)->not->toBeNull()
        // a screen without a record keeps the record opened before
        ->and($usage->record_id)->toBe($other->id)
        ->and($usage->record_type)->toBe($other->getMorphClass())
        ->and(AppUsage::query()->where('user_id', $user->id)->where('app_code', 'sales-entry')->value('visits'))->toBe(1)
        ->and(AppUsage::query()->where('user_id', $other->id)->value('visits'))->toBe(1)
        ->and(AppUsage::query()->count())->toBe(3);
});
