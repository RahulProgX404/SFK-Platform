<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Widgets;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Application\TeamDashboard;
use Modules\Analytics\Filament\Dashboard\DashboardCard;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Application\Cache\DataCache;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\AppUsage;
use Modules\Core\Models\BusinessApp;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;

/**
 * クイックアクション (row 3, every user; design_ref quick-actions.png): what waits for this user and is done with one
 * click — the next step of 16. 申請管理 and 20. 消耗品発注 (TeamDashboard::pendingActions()). When nothing waits
 * (bug.md 2026-11-06): the five apps the user opened last (AppUsageLog), each with the record last opened in it —
 * a click opens that record (the app's list when no record was opened or it cannot be opened any more); the card is then
 * called 最近表示した項目.
 */
final class QuickActionsCard extends DashboardCard
{
    protected static ?int $sort = 9;

    protected string $view = 'analytics::widgets.quick-actions';

    /** Row 3: last — first for users without 財務分析を表示 (bug.md 2026-11-06). */
    public static function getSort(): int
    {
        return (self::actor()?->seesFinancialAnalysis() ?? false) ? 9 : 7;
    }

    /** 個人 layout (bug.md 2026-11-09): row 2, after 売上の推移. */
    protected static function sortInLayout(bool $personal): int
    {
        return $personal ? 5 : self::getSort();
    }

    public function runAction(string $key): void
    {
        $user = self::actor();
        if ($user === null) {
            return;
        }

        try {
            $message = app(TeamDashboard::class)->run($key, $user);
        } catch (HrActionNotAllowed|PurchasingActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($message)->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = self::actor();
        $service = app(TeamDashboard::class);
        $actions = $user !== null ? $service->pendingActions($user) : [];

        return [
            'actions' => $actions,
            'more' => $user !== null && count($actions) >= 6 ? max(0, $service->pendingCount($user) - count($actions)) : 0,
            'recent' => $user !== null && $actions === [] ? self::recentRecords($user) : [],
            // 個人: the rest of row 2 next to 売上の推移
            'span' => $this->inPersonalLayout() ? 4 : $this->teamCardSpan(),
        ];
    }

    /**
     * The apps the user opened last that they may still open, newest first, with the record last opened in each.
     *
     * @return list<array{code: string, app: string, icon: string, record: int|null, title: string|null, url: string, at: string}>
     */
    public static function recentRecords(User $user, int $limit = 5): array
    {
        $usages = app(AppUsageLog::class)->recent($user, $limit * 3);
        // from the cache (DataCache) for these visits: the visits are written without model events, so they are the key
        $visits = md5((string) json_encode(array_map(fn (AppUsage $usage): array => [$usage->app_code, $usage->record_id, (string) $usage->record_visited_at, (string) $usage->last_visited_at], $usages)));

        return DataCache::remember('recent-records:'.$user->id.':'.$limit.':'.$visits, fn (): array => self::readRecent($usages, $limit));
    }

    /**
     * @param  list<AppUsage>  $usages
     * @return list<array{code: string, app: string, icon: string, record: int|null, title: string|null, url: string, at: string}>
     */
    private static function readRecent(array $usages, int $limit): array
    {
        $list = [];
        foreach ($usages as $usage) {
            $resource = AppResource::forCode($usage->app_code);
            $listUrl = $resource !== null ? self::appUrl($usage->app_code) : null;
            if ($resource === null || $listUrl === null) {
                continue;
            }

            $record = $usage->record_id !== null ? self::visibleRecord($resource, $usage->record_type, $usage->record_id) : null;
            $app = BusinessApp::forCode($usage->app_code);
            $list[] = [
                'code' => $usage->app_code,
                'app' => $resource::displayName(),
                'icon' => $app?->iconText() ?? mb_substr($resource::displayName(), 0, 2),
                'record' => $record !== null ? (int) $record->getKey() : null,
                'title' => $record !== null ? self::recordTitle($resource, $record) : null,
                'url' => $record !== null && $resource::hasPage('view') && $resource::canView($record)
                    ? $resource::getUrl('view', ['record' => $record])
                    : $listUrl,
                'at' => ($usage->record_id !== null && $record !== null ? $usage->record_visited_at : $usage->last_visited_at)?->format('n/j H:i') ?? '',
            ];
            if (count($list) === $limit) {
                break;
            }
        }

        return $list;
    }

    /** @param  class-string<AppResource>  $resource */
    private static function visibleRecord(string $resource, ?string $type, int $id): ?Model
    {
        $model = $resource::getModel();
        if ($type !== null && $type !== (new $model)->getMorphClass()) {
            return null;
        }

        return $resource::onlyVisible($model::query())->find($id);
    }

    /** @param  class-string<AppResource>  $resource */
    private static function recordTitle(string $resource, Model $record): ?string
    {
        $title = match (true) {
            method_exists($resource, 'titleOf') => $resource::titleOf($record),
            $resource::hasRecordTitle() => $resource::getRecordTitle($record),
            default => null,
        };
        $title = trim(strip_tags((string) $title));

        return $title !== '' && $title !== (string) $record->getKey() ? $title : null;
    }
}
