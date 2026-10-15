<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\Space;

/**
 * Spaces of one dashboard, maintained by administrators in that dashboard's ダッシュボード設定: name, members
 * and apps. An app belongs to at most one space, so only unplaced apps (or the space's own) can be added.
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

        // Its apps become unplaced again (space_id is nulled by the foreign key).
        $space->delete();
    }

    /** @param  list<int>  $appIds */
    private function syncApps(Space $space, array $appIds): void
    {
        $taken = BusinessApp::query()
            ->whereIn('id', $appIds)
            ->whereNotNull('space_id')
            ->where('space_id', '!=', $space->id)
            ->pluck('name');

        if ($taken->isNotEmpty()) {
            throw new AppActionNotAllowed('他のスペースに配置済みのアプリは追加できません：'.$taken->implode('、'));
        }

        BusinessApp::query()->where('space_id', $space->id)->whereNotIn('id', $appIds)->update(['space_id' => null]);
        BusinessApp::query()->whereIn('id', $appIds)->update(['space_id' => $space->id]);
    }

    /**
     * Apps that can be placed in the space: unplaced ones and its own.
     *
     * @return array<int, string> id => "name (code)"
     */
    public static function placeableApps(?Space $space): array
    {
        return BusinessApp::query()
            ->where(fn ($query) => $query->whereNull('space_id')->when($space !== null, fn ($query) => $query->orWhere('space_id', $space?->id)))
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (BusinessApp $app): array => [$app->id => "{$app->name}（{$app->code}）"])
            ->all();
    }
}
