<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\RecordAccess;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Records\RecordRules;
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

    /**
     * The name the app is built with (e.g. 「01. 取引先マスタ」; the registry starts with it, AppDiscovery); defaults to the
     * navigation label of the code. What users see is displayName().
     */
    public static function appName(): string
    {
        return (string) parent::getNavigationLabel();
    }

    /** The name shown for the app: its registry name — an administrator may change it (アプリの設定) — else appName(). */
    public static function displayName(): string
    {
        $name = static::app()?->name;

        return $name !== null && $name !== '' ? $name : static::appName();
    }

    /** The sidebar shows the app's displayName() (renamed by an administrator, user 2026-11-09). */
    public static function getNavigationLabel(): string
    {
        return static::displayName();
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
                return $resource::newRecordUrl($parameters, $panel->getId());
            }
        }

        return null;
    }

    /**
     * Livewire component (class) of an app that adds records in a panel over the screen instead of a create page (e.g.
     * 08. 日報入力). Core then opens it from 新規, the dashboard's 新規作成 (event QUICK_CREATE_EVENT with the app code)
     * and the list's ?new=1 (再利用: ?new=1&duplicate=<id>); the component is mounted with `app`, `open` and `duplicate`.
     *
     * @return class-string|null
     */
    public static function quickCreateComponent(): ?string
    {
        return null;
    }

    /**
     * The app as one screen (e.g. the 製袋予定表 of 12): its list shows only its first view — no 一覧 tabs, no 絞り込む, no
     * toolbar — and ⚙ (administrators) sits in the header in place of 新規 with アプリの権限 only, on every screen of the app.
     */
    public static function isSingleScreen(): bool
    {
        return false;
    }

    /**
     * What a record of the app must be wherever it is saved (quick entry, edit page, インライン編集): RecordRules; null =
     * only the rules of the inputs.
     */
    public static function recordRules(): ?RecordRules
    {
        return null;
    }

    /** Browser event opening an app's quick-create panel, detail { app: <app code> }. */
    public const QUICK_CREATE_EVENT = 'sfk-quick-create';

    /**
     * Where a new record of this app is added: its create page, else its list opening the quick-create panel
     * (?new=1); null when it has neither or the user may not add records.
     *
     * @param  array<string, mixed>  $parameters  query of the page (e.g. duplicate)
     */
    public static function newRecordUrl(array $parameters = [], ?string $panel = null): ?string
    {
        if (! static::canCreate()) {
            return null;
        }
        if (static::hasPage('create')) {
            return static::getUrl('create', $parameters, panel: $panel);
        }

        return static::quickCreateComponent() !== null ? static::getUrl('index', ['new' => 1, ...$parameters], panel: $panel) : null;
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

    /**
     * The record of a URL (/{record}): a key that is no whole number is no record of an app with numeric ids — e.g.
     * /create of an app without a create page (12. 製袋予定管理, 08. 日報入力) — so 404, not a database error (PostgreSQL
     * rejects 'create' for a bigint).
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        $model = app(static::getModel());
        if ($model->getKeyType() === 'int' && static::getRecordRouteKeyName() === null && ! ctype_digit((string) $key)) {
            return null;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    /** The records of the app the user may see: the レコードのアクセス権 narrow the query (RecordAccess::scope()). */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        $app = $user instanceof User && RecordAccess::restricts($user) ? static::app() : null;

        return $app === null ? $query : RecordAccess::scope($query, $user, $app, static::filterFieldMap());
    }

    /**
     * The app's records the signed-in user may see, for lists of them on other screens (related records, 履歴 sections,
     * searches): app permissions and レコードのアクセス権 apply, and there is nothing without 閲覧 — as the related lists
     * of the legacy system showed only the records the viewer may see.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  a query of the app's model
     * @return Builder<TModel>
     */
    public static function onlyVisible(Builder $query): Builder
    {
        if (! static::canViewAny()) {
            return $query->whereRaw('1 = 0');
        }

        // through getEloquentQuery(): the app's own narrowing (overrides included) and the レコードのアクセス権
        $key = $query->getModel()->getQualifiedKeyName();

        return $query->whereIn($key, static::getEloquentQuery()->select($key));
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
