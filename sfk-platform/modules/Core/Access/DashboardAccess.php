<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use WeakMap;

/**
 * Which dashboards (Filament panels) a user may open.
 *
 * - ユーザー種別 super_admin: every dashboard.
 * - Administrator-only dashboards (`<panel-id>.panel.admin_only` = true, e.g. 共通管理): super_admin
 *   and admin only. They are never offered on the user or category forms.
 * - Every other dashboard: the user must be able to sign in (在籍状況) and have it granted — directly on the
 *   user (`users.dashboards`, chosen on the user form) or through their 従業員カテゴリ
 *   (`employee_categories.dashboards`).
 *
 * The lookup runs once per user per request: the dashboard switcher checks every panel on every page.
 */
final class DashboardAccess
{
    /** @var WeakMap<Request, array<int, list<string>>>|null user id => granted dashboards, per request */
    private static ?WeakMap $perRequest = null;

    public static function allows(User $user, Panel|string $panel): bool
    {
        $panelId = $panel instanceof Panel ? $panel->getId() : $panel;

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->employment_status->canSignIn()) {
            return false;
        }

        if (self::isAdminOnly($panelId)) {
            return $user->user_type->isAdministrator();
        }

        return in_array($panelId, self::grantedDashboards($user), true);
    }

    /**
     * The user's granted dashboards (own + category), memoized on the current request (a new request — including each
     * request in a test — starts fresh, so category changes are picked up on the next page).
     *
     * @return list<string>
     */
    private static function grantedDashboards(User $user): array
    {
        self::$perRequest ??= new WeakMap;
        $request = request();
        $cached = self::$perRequest[$request] ?? [];

        if (! array_key_exists($user->id, $cached)) {
            $cached[$user->id] = $user->grantedDashboards();
            self::$perRequest[$request] = $cached;
        }

        return $cached[$user->id];
    }

    public static function isAdminOnly(string $panelId): bool
    {
        return (bool) config("{$panelId}.panel.admin_only", false);
    }

    /**
     * Dashboards that can be granted to a user or a 従業員カテゴリ (everything except the administrator-only ones).
     *
     * @return array<string, string> panel id => label
     */
    public static function grantableOptions(): array
    {
        $options = [];
        foreach (Filament::getPanels() as $id => $panel) {
            if (! self::isAdminOnly($id)) {
                $options[$id] = self::label($panel);
            }
        }

        return $options;
    }

    /** Card label from `<panel-id>.portal.label`, falling back to the panel's brand name. */
    public static function label(Panel $panel): string
    {
        return (string) (config("{$panel->getId()}.portal.label") ?? $panel->getBrandName());
    }
}
