<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Filters\RecordFilterQuery;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Filters\RecordFilter;
use Modules\Core\Models\AppRecordRule;
use Modules\Core\Models\AppRecordRuleEntry;
use Modules\Core\Models\BusinessApp;
use WeakMap;

/**
 * レコードのアクセス権 (permission.md "Record"), on top of the app permissions (AppAccess), like the legacy system:
 *
 * - the rules of an app are read in order; the first rule whose condition (a 絞り込む filter, none = every record)
 *   a record matches decides for that record — a record no rule matches follows the app permissions only;
 * - in that rule the first entry that applies to the user decides (作成者: the user created the record), and a user no
 *   entry applies to gets nothing on those records;
 * - the rights only narrow the app permissions (閲覧 needs the app's 閲覧, 編集 its 編集, 削除 its 削除);
 * - administrators are not restricted (as with the app permissions).
 *
 * Conditions are evaluated as「key in (records matching the condition)」so that a record with blank values is never
 * dropped by a negated comparison.
 */
final class RecordAccess
{
    public const VIEW = 'view';

    public const EDIT = 'edit';

    public const DELETE = 'delete';

    /** @var WeakMap<Request, array<int, list<AppRecordRule>>>|null rules by app id, per request */
    private static ?WeakMap $rules = null;

    /** @var array<string, bool> table => has created_by */
    private static array $creatorColumns = [];

    public static function restricts(User $user): bool
    {
        return ! $user->user_type->isAdministrator();
    }

    /**
     * The user's rights on a record from the rules, or null when no rule covers the record (app permissions only).
     *
     * @param  array<string, FilterField>  $fields  the app's filter fields by name (its list page)
     * @return array{view: bool, edit: bool, delete: bool}|null
     */
    public static function rights(User $user, BusinessApp $app, Model $record, array $fields): ?array
    {
        if (! self::restricts($user)) {
            return null;
        }

        foreach (self::rulesOf($app) as $rule) {
            $matches = self::hasFilter($rule)
                ? $record->newQuery()->whereKey($record->getKey())->where(fn (Builder $query) => self::condition($query, $rule, $fields, $user))->exists()
                : true;

            if ($matches) {
                $mine = self::isCreator($record, $user);
                $entry = self::entryFor($rule, $user, $mine);

                return [
                    self::VIEW => $entry !== null && $entry->can_view,
                    self::EDIT => $entry !== null && $entry->can_edit,
                    self::DELETE => $entry !== null && $entry->can_delete,
                ];
            }
        }

        return null;
    }

    /**
     * Whether the rules allow the right on the record (true when no rule covers it).
     *
     * @param  array<string, FilterField>  $fields
     */
    public static function allows(User $user, BusinessApp $app, Model $record, array $fields, string $right): bool
    {
        $rights = self::rights($user, $app, $record, $fields);

        return $rights === null || $rights[$right];
    }

    /**
     * Limits a query of the app's records to the ones the rules let the user see.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, FilterField>  $fields
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, User $user, BusinessApp $app, array $fields): Builder
    {
        $rules = self::rulesOf($app);
        if ($rules === [] || ! self::restricts($user)) {
            return $query;
        }

        $model = $query->getModel();
        $key = $model->getQualifiedKeyName();
        $creatorColumn = self::creatorColumn($model);
        $matching = fn (AppRecordRule $rule) => $model->newQuery()
            ->select($model->getKeyName())
            ->when(self::hasFilter($rule), fn (Builder $sub) => $sub->where(fn (Builder $inner) => self::condition($inner, $rule, $fields, $user)));

        return $query->where(function (Builder $visible) use ($rules, $key, $creatorColumn, $matching, $user): void {
            $earlier = [];
            foreach ($rules as $rule) {
                $mine = self::entryFor($rule, $user, true);
                $others = self::entryFor($rule, $user, false);
                $mineView = $mine !== null && $mine->can_view;
                $othersView = $others !== null && $others->can_view;
                if ($creatorColumn === null) {
                    $mineView = $othersView;
                }

                $visible->orWhere(function (Builder $branch) use ($rule, $earlier, $key, $matching, $mineView, $othersView, $creatorColumn, $user): void {
                    foreach ($earlier as $previous) {
                        $branch->whereNotIn($key, $matching($previous));
                    }
                    if (self::hasFilter($rule)) {
                        $branch->whereIn($key, $matching($rule));
                    }

                    if ($mineView && $othersView) {
                        return;
                    }
                    if (! $mineView && ! $othersView) {
                        $branch->whereRaw('1 = 0');
                    } elseif ($mineView) {
                        $branch->where((string) $creatorColumn, $user->id);
                    } else {
                        $branch->where(fn (Builder $others) => $others->whereNull((string) $creatorColumn)->orWhere((string) $creatorColumn, '!=', $user->id));
                    }
                });

                if (! self::hasFilter($rule)) {
                    return; // every record is covered: later rules and the fallback never apply
                }
                $earlier[] = $rule;
            }

            // records no rule covers: the app permissions only
            $visible->orWhere(function (Builder $rest) use ($earlier, $key, $matching): void {
                foreach ($earlier as $previous) {
                    $rest->whereNotIn($key, $matching($previous));
                }
            });
        });
    }

    /** @return list<AppRecordRule> the app's rules with their entries, in order (once per request) */
    public static function rulesOf(BusinessApp $app): array
    {
        self::$rules ??= new WeakMap;
        $request = request();
        $cached = self::$rules[$request] ?? [];

        if (! array_key_exists($app->id, $cached)) {
            $cached[$app->id] = array_values($app->recordRules()->with('entries')->get()->all());
            self::$rules[$request] = $cached;
        }

        return $cached[$app->id];
    }

    /** The first entry of the rule that applies to the user, for a record the user created ($mine) or not. */
    private static function entryFor(AppRecordRule $rule, User $user, bool $mine): ?AppRecordRuleEntry
    {
        $categoryIds = $user->employeeCategories()->pluck('employee_categories.id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach ($rule->entries as $entry) {
            if ($entry->subject_type === AccessSubject::Creator ? $mine : $entry->appliesTo($user, $categoryIds)) {
                return $entry;
            }
        }

        return null;
    }

    private static function hasFilter(AppRecordRule $rule): bool
    {
        return is_array($rule->filter) && ($rule->filter['conditions'] ?? []) !== [];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, FilterField>  $fields
     */
    private static function condition(Builder $query, AppRecordRule $rule, array $fields, User $user): void
    {
        $filter = RecordFilter::fromArray($rule->filter, array_map(fn (FilterField $field) => $field->type, $fields), []);
        if (! $filter->hasConditions()) {
            $query->whereRaw('1 = 0'); // a condition on fields the app no longer has covers nothing

            return;
        }

        RecordFilterQuery::filter($query, $filter, $fields, $user->id, CarbonImmutable::now());
    }

    private static function isCreator(Model $record, User $user): bool
    {
        return self::creatorColumn($record) !== null && (int) $record->getAttribute('created_by') === $user->id;
    }

    private static function creatorColumn(Model $model): ?string
    {
        $table = $model->getTable();
        self::$creatorColumns[$table] ??= Schema::hasColumn($table, 'created_by');

        return self::$creatorColumns[$table] ? $model->qualifyColumn('created_by') : null;
    }
}
