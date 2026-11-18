<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\AppAccess;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\BusinessApp;
use Throwable;

/**
 * ERP records as small structured cards (e.g. 注文情報: 注番, 得意先, 納期, 状態 — shared in 社内メッセージ). Each module
 * declares the card of its app (`RecordCards::register('order-entry', fn (Order $o) => [...])`, in its provider); the card
 * is always drawn from the record as it is now, for one viewer, through the app's screens:
 * - the record must be one the viewer may open (app permissions, the app's narrowing, レコードのアクセス権 — Core
 *   RecordDirectory), else the card says only that it is not available;
 * - each field passes the viewer's フィールドのアクセス権 (a field they may not see is left out);
 * - a deleted record says so. Nothing of the record is stored with whoever shares it.
 */
final class RecordCards
{
    /** @var array<string, Closure> app code => fn (Model): list<array{field: string, label: string, value: string|null}> */
    private static array $cards = [];

    /**
     * @param  Closure  $fields  fn (<the app's model> $record): list<array{field: string, label: string, value: string|null}> —
     *                           field = the form attribute the フィールドのアクセス権 apply to
     */
    public static function register(string $appCode, Closure $fields): void
    {
        self::$cards[$appCode] = $fields;
    }

    public static function has(string $appCode): bool
    {
        return isset(self::$cards[$appCode]);
    }

    /**
     * The apps whose records the user may share (registered, with a record page, opened by the user).
     *
     * @return list<array{code: string, name: string}>
     */
    public static function appsFor(User $user): array
    {
        $apps = [];
        foreach (array_keys(self::$cards) as $code) {
            $resource = AppResource::forCode($code);
            if ($resource !== null && $resource::hasPage('view') && AppAccess::can($user, $code, AppAbility::View)) {
                $apps[] = ['code' => $code, 'name' => $resource::displayName()];
            }
        }

        return $apps;
    }

    /**
     * The card of a record for this viewer.
     *
     * @return array{state: string, app: string, code: string, id: string, title?: string, url?: string, fields?: list<array{label: string, value: string}>}
     *                                                                                                                                                       state: ok | denied (not theirs to see) | missing (deleted, or the app is gone)
     */
    public static function card(User $viewer, string $appCode, int|string $id): array
    {
        $resource = AppResource::forCode($appCode);
        $base = ['app' => $resource !== null ? $resource::displayName() : $appCode, 'code' => $appCode, 'id' => (string) $id];
        if ($resource === null || ! isset(self::$cards[$appCode])) {
            return ['state' => 'missing', ...$base];
        }

        try {
            $record = $resource::getModel()::query()->find($id);
        } catch (Throwable) {
            $record = null;
        }
        if (! $record instanceof Model) {
            return ['state' => 'missing', ...$base];
        }
        if (! AppAccess::can($viewer, $appCode, AppAbility::View) || ! app(RecordDirectory::class)->visibleTo($viewer, $record)) {
            return ['state' => 'denied', ...$base];
        }

        $app = BusinessApp::forCode($appCode);
        $fields = [];
        foreach ((self::$cards[$appCode])($record) as $field) {
            if ($app !== null && ! FieldAccess::level($viewer, $app, $field['field'])->canView()) {
                continue;
            }
            if ($field['value'] !== null && $field['value'] !== '') {
                $fields[] = ['label' => $field['label'], 'value' => $field['value']];
            }
        }

        return [
            'state' => 'ok',
            ...$base,
            'title' => trim(strip_tags((string) $resource::getRecordTitle($record))),
            'url' => $resource::getUrl('view', ['record' => $record], panel: self::panelOf($resource)),
            'fields' => $fields,
        ];
    }

    /**
     * Records of an app the user may see, for a picker: newest first, by the words in the record title / searchable
     * columns. Asked as the user (the screens' query: app permissions, narrowing, レコードのアクセス権).
     *
     * @return list<array{id: string, title: string, detail: string}>
     */
    public static function search(User $user, string $appCode, string $words, int $limit = 20): array
    {
        $resource = AppResource::forCode($appCode);
        if ($resource === null || ! isset(self::$cards[$appCode]) || ! AppAccess::can($user, $appCode, AppAbility::View)) {
            return [];
        }

        $guard = auth()->guard('web');
        $previous = $guard->user();
        $guard->setUser($user);
        try {
            /** @var Builder<Model> $query */
            $query = $resource::getEloquentQuery();
            $columns = array_values(array_filter([...$resource::getGloballySearchableAttributes(), $resource::getRecordTitleAttribute()], fn (mixed $column): bool => is_string($column) && ! str_contains($column, '.')));
            $columns = array_values(array_unique($columns));
            foreach (preg_split('/[\s\x{3000}]+/u', trim($words)) ?: [] as $word) {
                if ($word === '' || $columns === []) {
                    continue;
                }
                $query->where(function (Builder $either) use ($columns, $word): void {
                    foreach ($columns as $i => $column) {
                        SearchQuery::where($either, $either->getModel()->qualifyColumn($column), $word, $i === 0 ? 'and' : 'or');
                    }
                });
            }

            $app = BusinessApp::forCode($appCode);

            return $query->reorder()->orderByDesc($query->getModel()->getQualifiedKeyName())->limit($limit)->get()
                ->map(function (Model $record) use ($resource, $appCode, $user, $app): array {
                    $fields = array_values(array_filter((self::$cards[$appCode])($record), fn (array $field): bool => $field['value'] !== null && $field['value'] !== ''
                        && ($app === null || FieldAccess::level($user, $app, $field['field'])->canView())));

                    return [
                        'id' => (string) $record->getKey(),
                        'title' => trim(strip_tags((string) $resource::getRecordTitle($record))),
                        'detail' => implode('・', array_map(fn (array $field): string => (string) $field['value'], array_slice($fields, 1, 3))),
                    ];
                })->all();
        } catch (Throwable) {
            return [];
        } finally {
            $previous !== null ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }

    /** @param  class-string<AppResource>  $resource */
    private static function panelOf(string $resource): ?string
    {
        foreach (Filament::getPanels() as $panel) {
            if (in_array($resource, $panel->getResources(), true)) {
                return $panel->getId();
            }
        }

        return null;
    }

    /** Forgets the registrations (tests). */
    public static function flush(): void
    {
        self::$cards = [];
    }
}
