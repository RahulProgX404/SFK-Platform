<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;

/**
 * The app registry follows the code: an app appears (開発中, outside any dashboard or space) as soon as a screen
 * for it is built (an AppResource with its code) and disappears, with its permissions, when that code is
 * deleted (AppDiscovery, syncWithCode). People only move apps between statuses — AppStatus::targets() for the
 * actor's ユーザー種別 — and super admins may set the icon text.
 */
final class AppLifecycle
{
    /**
     * @param  array<string, string>  $definitions  code => name, from AppDiscovery
     * @param  array<string, array<string, bool>>  $settingDefaults  code => その他の設定 a new app starts with
     *                                                               (AppDiscovery::settingDefaults())
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncWithCode(array $definitions, array $settingDefaults = []): array
    {
        return DB::transaction(function () use ($definitions, $settingDefaults): array {
            $existing = BusinessApp::query()->get()->keyBy('code');
            $added = [];

            foreach ($definitions as $code => $name) {
                $app = $existing->get($code);

                if ($app === null) {
                    $app = new BusinessApp([
                        'code' => $code,
                        'name' => $name,
                        'sort_order' => ((int) BusinessApp::query()->max('sort_order')) + 1,
                    ]);
                    $app->forceFill([
                        'status' => AppStatus::Developing,
                        'space_id' => null,
                        ...array_intersect_key($settingDefaults[$code] ?? [], array_flip(BusinessApp::SETTINGS)),
                    ])->save();
                    $added[] = $code;
                } elseif ($app->name !== $name) {
                    $app->update(['name' => $name]);
                }
            }

            $removed = $existing->keys()->diff(array_keys($definitions))->values()->all();
            BusinessApp::query()->whereIn('code', $removed)->delete();

            return ['added' => $added, 'removed' => array_values(array_map('strval', $removed))];
        });
    }

    public function updateIconText(BusinessApp $app, ?string $iconText, User $actor): BusinessApp
    {
        if (! $actor->isSuperAdmin()) {
            throw new AppActionNotAllowed('アプリを編集できるのはスーパー管理者のみです。');
        }

        $app->update(['icon_text' => filled($iconText) ? $iconText : null]);

        return $app;
    }

    public function move(BusinessApp $app, AppStatus $to, User $actor): BusinessApp
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('アプリの状態を変更する権限がありません。');
        }

        if (! $app->status->canMoveTo($to, $actor->isSuperAdmin())) {
            throw new AppActionNotAllowed("「{$app->status->label()}」から「{$to->label()}」へは移動できません。");
        }

        $app->forceFill(['status' => $to])->save();

        return $app;
    }

    /**
     * Puts the given apps in this order (アプリ list of a dashboard's ポータル, administrators). Other apps — e.g. those
     * of other dashboards or hidden from the actor — keep their places; the whole registry is then numbered 1, 2, …
     *
     * @param  list<int>  $ids
     */
    public function reorder(array $ids, User $actor): void
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('アプリの並び順を変更する権限がありません。');
        }

        DB::transaction(function () use ($ids): void {
            $all = array_map('intval', BusinessApp::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->pluck('id')->all());
            $moved = array_values(array_unique(array_intersect($ids, $all)));

            // the slots of the moved apps, in registry order, are filled with them in the new order
            $order = [];
            $next = 0;
            foreach ($all as $id) {
                $order[] = in_array($id, $moved, true) ? $moved[$next++] : $id;
            }

            foreach ($order as $index => $id) {
                BusinessApp::query()->whereKey($id)->where('sort_order', '!=', $index + 1)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
