<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Modules\Core\Access\DashboardAccess;

/**
 * A user's name that opens the ユーザー情報 popup (legacy user information pop-up): icon, name, 従業員カテゴリ, 役職 and
 * the link to the profile when the viewer may open it (their own プロフィール, or the user's page in 設定 for those
 * who may open that dashboard). Use html() in table columns and record entries (->html()) and in Blade views.
 */
final class UserPopup
{
    public const SETTINGS_PANEL = 'settings';

    public const SETTINGS_USER_ROUTE = 'filament.settings.resources.users.view';

    /** @param  string|null  $label  shown instead of the name (e.g. 「【0101】山田太郎」) */
    public static function html(?User $user, ?string $label = null): HtmlString
    {
        if ($user === null) {
            return new HtmlString('');
        }

        return new HtmlString(view('core::components.user-popup', [
            'user' => $user,
            'label' => $label ?? $user->name,
            'profileUrl' => self::profileUrl($user),
        ])->render());
    }

    /**
     * Several users, one per line.
     *
     * @param  iterable<User>  $users
     * @param  (callable(User): string)|null  $label
     */
    public static function list(iterable $users, ?callable $label = null): HtmlString
    {
        $lines = [];
        foreach ($users as $user) {
            $lines[] = '<span class="sfk-user-line">'.self::html($user, $label !== null ? $label($user) : null)->toHtml().'</span>';
        }

        return new HtmlString(implode('', $lines));
    }

    public static function profileUrl(User $user): ?string
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            return null;
        }

        if ($viewer->is($user)) {
            return Filament::getCurrentPanel()?->getProfileUrl();
        }

        if (Route::has(self::SETTINGS_USER_ROUTE) && DashboardAccess::allows($viewer, self::SETTINGS_PANEL)) {
            return route(self::SETTINGS_USER_ROUTE, ['record' => $user]);
        }

        return null;
    }
}
