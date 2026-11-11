<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\AppUsage;

/**
 * Which apps a user opens (bug.md 2026-11-05): written when an app screen is first drawn (HasAppChrome), read for
 * よく使うアプリ (the アプリ panel of the dashboard) and the recent records (クイックアクション when nothing waits). Only the
 * user's own use is kept and shown back to them.
 */
final class AppUsageLog
{
    /**
     * One opening of an app screen; with the record of a record / edit page. One statement (insert, or add 1 on the
     * unique user_id + app_code) — it runs on every app screen, and each query to a remote database is a round trip.
     */
    public function touch(User $user, string $appCode, ?Model $record = null): void
    {
        $now = now();
        $values = ['user_id' => $user->id, 'app_code' => $appCode, 'visits' => 1, 'last_visited_at' => $now];
        $update = ['visits' => DB::raw('app_usages.visits + 1'), 'last_visited_at' => $now];
        if ($record !== null && $record->exists) {
            $recordValues = ['record_type' => $record->getMorphClass(), 'record_id' => (int) $record->getKey(), 'record_visited_at' => $now];
            $values += $recordValues;
            $update += $recordValues;
        }

        AppUsage::query()->upsert([$values], ['user_id', 'app_code'], $update);
    }

    /**
     * The apps the user opens most, by code (most first).
     *
     * @param  list<string>|null  $codes  only these apps
     * @return list<string>
     */
    public function mostUsed(User $user, ?array $codes = null, int $limit = 5): array
    {
        return AppUsage::query()
            ->where('user_id', $user->id)
            ->when($codes !== null, fn ($query) => $query->whereIn('app_code', $codes))
            ->orderByDesc('visits')->orderByDesc('last_visited_at')
            ->limit($limit)
            ->pluck('app_code')
            ->all();
    }

    /**
     * The apps everyone opens most, by code (most first; ties by code).
     *
     * @param  list<string>|null  $codes  only these apps
     * @return list<string>
     */
    public function popular(?array $codes = null, int $limit = 5): array
    {
        return AppUsage::query()
            ->when($codes !== null, fn ($query) => $query->whereIn('app_code', $codes))
            ->groupBy('app_code')
            ->selectRaw('app_code, sum(visits) as total')
            ->orderByDesc('total')->orderBy('app_code')
            ->limit($limit)
            ->pluck('app_code')
            ->all();
    }

    /**
     * よく使うアプリ (bug.md 2026-11-06): the apps the user opens most; while they have opened none of $codes yet, the apps
     * everyone opens most.
     *
     * @param  list<string>  $codes  the apps the user may open
     * @return list<string>
     */
    public function mostUsedOrPopular(User $user, array $codes, int $limit = 5): array
    {
        $own = $this->mostUsed($user, $codes, $limit);

        return $own !== [] ? $own : $this->popular($codes, $limit);
    }

    /**
     * The apps the user opened last, with the record last opened in each (newest first).
     *
     * @return list<AppUsage>
     */
    public function recent(User $user, int $limit = 5): array
    {
        return AppUsage::query()
            ->where('user_id', $user->id)
            ->whereNotNull('last_visited_at')
            ->orderByDesc('last_visited_at')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }
}
