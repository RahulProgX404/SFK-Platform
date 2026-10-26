<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\AppFieldPermission;
use Modules\Core\Models\BusinessApp;
use WeakMap;

/**
 * フィールドのアクセス権 (permission.md "Field"), like the legacy system: a field without entries can be edited by everyone
 * who may edit the record; a field with entries follows the first entry that applies to the user (編集 / 閲覧 / なし),
 * and a user none applies to gets なし. Administrators are not restricted (as with the app permissions).
 *
 * Applied by Core: RecordSections (forms and record pages — なし hides the field, 閲覧 disables its input so it is
 * not saved) and AppListRecords (columns, 絞り込む fields, インライン編集, ファイルに書き出す).
 */
final class FieldAccess
{
    /** @var WeakMap<Request, array<string, array<string, FieldAccessLevel>>>|null "userId:appId" => levels, per request */
    private static ?WeakMap $perRequest = null;

    /**
     * The user's level for every field with entries (fields not listed: 編集).
     *
     * @return array<string, FieldAccessLevel>
     */
    public static function levels(User $user, BusinessApp $app): array
    {
        if ($user->user_type->isAdministrator()) {
            return [];
        }

        self::$perRequest ??= new WeakMap;
        $request = request();
        $cached = self::$perRequest[$request] ?? [];
        $key = $user->id.':'.$app->id;

        if (! array_key_exists($key, $cached)) {
            $categoryIds = $user->employeeCategories()->pluck('employee_categories.id')->map(fn (mixed $id): int => (int) $id)->all();
            $levels = [];
            foreach ($app->fieldPermissions()->get()->groupBy('field') as $field => $entries) {
                $entry = $entries->first(fn (AppFieldPermission $entry): bool => $entry->appliesTo($user, $categoryIds));
                $levels[(string) $field] = $entry instanceof AppFieldPermission ? $entry->access : FieldAccessLevel::None;
            }
            $cached[$key] = $levels;
            self::$perRequest[$request] = $cached;
        }

        return $cached[$key];
    }

    public static function level(User $user, BusinessApp $app, string $field): FieldAccessLevel
    {
        return self::levels($user, $app)[$field] ?? FieldAccessLevel::Edit;
    }

    /**
     * The levels on the screen being built: the signed-in user in the app of the current app page (none elsewhere).
     *
     * @return array<string, FieldAccessLevel>
     */
    public static function onCurrentPage(): array
    {
        $user = auth()->user();
        $page = Livewire::current();
        // outside a Livewire component current() is false
        if (! $user instanceof User || $user->user_type->isAdministrator() || ! is_object($page) || ! method_exists($page, 'getResource')) {
            return [];
        }

        $resource = $page::getResource();
        $app = is_string($resource) && is_subclass_of($resource, AppResource::class) ? $resource::app() : null;

        return $app === null ? [] : self::levels($user, $app);
    }
}
