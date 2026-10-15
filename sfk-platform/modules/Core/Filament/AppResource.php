<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use App\Models\User;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\AppAccess;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;
use UnitEnum;

/**
 * Base class for the Filament resource(s) that implement an app (e.g. 01. 取引先マスタ): extend it instead of
 * Resource and return the app's registry code from appCode(). The resource then follows the app's permissions
 * (AppAccess): list/view need 閲覧, create 追加, edit 編集, delete 削除; it is listed in the sidebar under its
 * space's name, and only for users who may view it. Use appCan() for custom actions (e.g. CSV import →
 * AppAbility::Import, export → AppAbility::Export).
 */
abstract class AppResource extends Resource
{
    /** Registry code of the app this resource belongs to (see 共通管理 → アプリ設定). */
    abstract public static function appCode(): string;

    /** App name in the registry (e.g. 「01. 取引先マスタ」); defaults to the navigation label. */
    public static function appName(): string
    {
        return (string) static::getNavigationLabel();
    }

    /** Shown under the app's title bar (core::filament.app-header) unless the registry row has its own description. */
    public static function appDescription(): ?string
    {
        return null;
    }

    /** URL of the screen implementing the app in the given panel, or null while it is not implemented there. */
    public static function urlForApp(string $appCode, Panel $panel): ?string
    {
        foreach ($panel->getResources() as $resource) {
            if (is_subclass_of($resource, self::class) && $resource::appCode() === $appCode) {
                return $resource::getUrl('index', panel: $panel->getId());
            }
        }

        return null;
    }

    /**
     * Codes of the apps whose screens live in the given panel (dashboard).
     *
     * @return list<string>
     */
    public static function appCodesIn(Panel $panel): array
    {
        $codes = [];
        foreach ($panel->getResources() as $resource) {
            if (is_subclass_of($resource, self::class)) {
                $codes[] = $resource::appCode();
            }
        }

        return array_values(array_unique($codes));
    }

    /** The registry row of this app (null while not registered yet). */
    public static function app(): ?BusinessApp
    {
        return BusinessApp::query()->with('space')->where('code', static::appCode())->first();
    }

    public static function appCan(AppAbility $ability): bool
    {
        $user = auth()->user();

        return $user instanceof User && AppAccess::can($user, static::appCode(), $ability);
    }

    public static function canViewAny(): bool
    {
        return static::appCan(AppAbility::View);
    }

    public static function canView(Model $record): bool
    {
        return static::appCan(AppAbility::View);
    }

    public static function canCreate(): bool
    {
        return static::appCan(AppAbility::Create);
    }

    public static function canEdit(Model $record): bool
    {
        return static::appCan(AppAbility::Update);
    }

    public static function canDelete(Model $record): bool
    {
        return static::appCan(AppAbility::Delete);
    }

    public static function canDeleteAny(): bool
    {
        return static::appCan(AppAbility::Delete);
    }

    /** Listed in the sidebar like on the dashboard: every stage for super admins, else 公開中 / メンテナンス中. */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && AppAccess::listsOnDashboard($user, static::app());
    }

    /** Super admins see the stage of apps that are not 公開中 (e.g. 「テスト中」). */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $app = static::app();

        if (! $user instanceof User || ! $user->isSuperAdmin() || $app?->status === AppStatus::Published) {
            return null;
        }

        return $app?->status->label() ?? AppStatus::Developing->label();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'gray';
    }

    /** The space the app is placed in, as the sidebar group. */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $space = static::app()?->space;

        return $space !== null ? $space->name : static::$navigationGroup;
    }
}
