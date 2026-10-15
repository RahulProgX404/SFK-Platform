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
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncWithCode(array $definitions): array
    {
        return DB::transaction(function () use ($definitions): array {
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
                    $app->forceFill(['status' => AppStatus::Developing, 'space_id' => null])->save();
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
}
