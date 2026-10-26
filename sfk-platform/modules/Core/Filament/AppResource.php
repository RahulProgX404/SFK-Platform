<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\RecordAccess;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Filament\AppPages\AppListRecords;
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

    /**
     * その他の設定 the app starts with when it is added to the registry, where its legacy app differs from the defaults
     * (all on), e.g. ['bulk_delete_enabled' => false]. Keys: BusinessApp::SETTINGS.
     *
     * @return array<string, bool>
     */
    public static function appSettingDefaults(): array
    {
        return [];
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
     * URL of the new-record page of an app in the given panel (e.g. a legacy app action starting a record of another app),
     * or null while the app is not there or the user may not add records to it.
     *
     * @param  array<string, mixed>  $parameters  query of the page
     */
    public static function createUrlForApp(string $appCode, Panel $panel, array $parameters = []): ?string
    {
        foreach ($panel->getResources() as $resource) {
            if (is_subclass_of($resource, self::class) && $resource::appCode() === $appCode) {
                return $resource::hasPage('create') && $resource::canCreate() ? $resource::getUrl('create', $parameters, panel: $panel->getId()) : null;
            }
        }

        return null;
    }

    /**
     * The resource implementing an app (any dashboard), e.g. for its fields in the アクセス権 settings.
     *
     * @return class-string<AppResource>|null
     */
    public static function forCode(string $appCode): ?string
    {
        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                if (is_subclass_of($resource, self::class) && $resource::appCode() === $appCode) {
                    return $resource;
                }
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
        return BusinessApp::forCode(static::appCode());
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
        return static::appCan(AppAbility::View) && static::recordAllows($record, RecordAccess::VIEW);
    }

    public static function canCreate(): bool
    {
        return static::appCan(AppAbility::Create);
    }

    public static function canEdit(Model $record): bool
    {
        return static::appCan(AppAbility::Update) && static::recordAllows($record, RecordAccess::EDIT);
    }

    public static function canDelete(Model $record): bool
    {
        return static::appCan(AppAbility::Delete) && static::recordAllows($record, RecordAccess::DELETE);
    }

    /** The records of the app the user may see: the レコードのアクセス権 narrow the query (RecordAccess::scope()). */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        $app = $user instanceof User && RecordAccess::restricts($user) ? static::app() : null;

        return $app === null ? $query : RecordAccess::scope($query, $user, $app, static::filterFieldMap());
    }

    /** Whether the レコードのアクセス権 allow the right (RecordAccess::VIEW / EDIT / DELETE) on the record. */
    public static function recordAllows(Model $record, string $right): bool
    {
        $user = auth()->user();
        $app = $user instanceof User && RecordAccess::restricts($user) ? static::app() : null;

        return $app === null || RecordAccess::allows($user, $app, $record, static::filterFieldMap(), $right);
    }

    /**
     * The 絞り込む fields of the app by name (its list page), which the conditions of the レコードのアクセス権 use.
     *
     * @return array<string, FilterField>
     */
    public static function filterFieldMap(): array
    {
        $page = static::listPage();
        $fields = [];
        foreach ($page?->filterFields() ?? [] as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /**
     * The fields of the app's records (name => label, the columns of its list), offered in フィールドのアクセス権.
     *
     * @return array<string, string>
     */
    public static function permissionFields(): array
    {
        return static::listPage()?->listColumns() ?? [];
    }

    /** An instance of the app's list page (its 一覧, 絞り込む fields and columns), or null when it has none. */
    public static function listPage(): ?AppListRecords
    {
        $registration = static::getPages()['index'] ?? null;
        $page = $registration?->getPage();

        return is_string($page) && is_subclass_of($page, AppListRecords::class) ? new $page : null;
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

    /** The space the app is shown under on this dashboard (BusinessApp::spaceOn()), as the sidebar group. */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $user = auth()->user();
        $space = static::app()?->spaceOn(Filament::getCurrentOrDefaultPanel()->getId(), $user instanceof User ? $user : null);

        return $space !== null ? $space->name : static::$navigationGroup;
    }
}
