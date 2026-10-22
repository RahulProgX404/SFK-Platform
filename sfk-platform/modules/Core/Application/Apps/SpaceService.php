<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;

/**
 * Spaces of one dashboard, maintained by administrators in that dashboard's ダッシュボード設定: name, members
 * and apps. An app may be placed in several spaces (of this dashboard and others), so every app can be added.
 */
final class SpaceService
{
    /**
     * @param  array{name: string, user_ids?: list<int|string>, app_ids?: list<int|string>}  $data
     */
    public function save(?Space $space, string $panelId, array $data, User $actor): Space
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('スペースを管理できるのは管理者のみです。');
        }

        return DB::transaction(function () use ($space, $panelId, $data): Space {
            $space ??= new Space(['panel_id' => $panelId, 'sort_order' => ((int) Space::query()->where('panel_id', $panelId)->max('sort_order')) + 1]);
            $space->fill(['name' => $data['name']])->save();

            $space->users()->sync(array_map('intval', $data['user_ids'] ?? []));
            $this->syncApps($space, array_map('intval', $data['app_ids'] ?? []));

            return $space;
        });
    }

    public function delete(Space $space, User $actor): void
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('スペースを管理できるのは管理者のみです。');
        }

        // Its apps leave it (the placements go with the space); they stay in their other spaces.
        $space->delete();
    }

    /** @param  list<int>  $appIds */
    private function syncApps(Space $space, array $appIds): void
    {
        $space->apps()->sync(BusinessApp::query()->whereIn('id', $appIds)->pluck('id')->all());
    }

    /**
     * Apps that can be placed in the space: every app (an app may be in several spaces).
     *
     * @return array<int, string> id => "name (code)"
     */
    public static function placeableApps(?Space $space): array
    {
        return BusinessApp::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (BusinessApp $app): array => [$app->id => "{$app->name}（{$app->code}）"])
            ->all();
    }
}
