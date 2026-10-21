<?php

declare(strict_types=1);

namespace Modules\Core\Application\Views;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Filters\RecordFilter;
use Modules\Core\Domain\Views\ListViewType;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;

/**
 * The 一覧 of an app (legacy アプリの設定 → 一覧): the views in dropdown order with （すべて） last, the topmost being the
 * default; 一覧を追加する / 編集 / 複製 (「… - コピー」) / 削除 and drag-and-drop order. The app's code-defined views are
 * stored the first time its views are read, after that only the stored rows count. Changes apply immediately.
 */
final class ListViewService
{
    public const MAX_NAME_LENGTH = 64;

    private const COPY_SUFFIX = ' - コピー';

    /**
     * The views of the app's list, （すべて） last. Without a registry row the code-defined views are used as they are.
     *
     * @param  list<ListView>  $defaults
     * @return list<ListView>
     */
    public function views(?BusinessApp $app, array $defaults, ListViewSchema $schema): array
    {
        $views = $app === null
            ? $defaults
            : array_map(fn (AppListView $view): ListView => ListView::fromModel($view), $this->stored($app, $defaults));

        return [...$views, ListView::all($schema->columnNames())];
    }

    /**
     * Stored views of the app, in order; stores the code-defined ones first if that has not happened yet.
     *
     * @param  list<ListView>  $defaults
     * @return list<AppListView>
     */
    public function stored(BusinessApp $app, array $defaults): array
    {
        if ($app->list_views_seeded_at === null) {
            DB::transaction(function () use ($app, $defaults): void {
                $locked = BusinessApp::query()->lockForUpdate()->findOrFail($app->id);
                if ($locked->list_views_seeded_at !== null) {
                    return;
                }

                $existing = $locked->listViews()->get();
                foreach ($existing as $index => $view) {
                    $view->update(['sort_order' => count($defaults) + $index + 1]);
                }
                foreach ($defaults as $index => $default) {
                    if ($existing->contains('name', $default->name)) {
                        continue;
                    }
                    $locked->listViews()->create([
                        'name' => $default->name,
                        'type' => $default->type,
                        'columns' => $default->columns,
                        'date_field' => $default->dateField,
                        'title_field' => $default->titleField,
                        'custom_view' => $default->customView,
                        'filter' => $default->filter,
                        'sort_order' => $index + 1,
                    ]);
                }
                $locked->forceFill(['list_views_seeded_at' => now()])->save();
            });
            $app->refresh();
        }

        return $app->listViews()->get()->all();
    }

    /**
     * 一覧を追加する (no $view) or 編集: validates the 一覧 screen and stores it; a new view goes to the end.
     *
     * @param  array<string, mixed>  $input  name, type, columns, dateField, titleField, filter
     *
     * @throws ListViewInvalid
     */
    public function save(BusinessApp $app, ?AppListView $view, array $input, ListViewSchema $schema, ?int $userId): AppListView
    {
        $errors = [];

        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        if ($name === '') {
            $errors['name'] = '一覧名を入力してください。';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = '一覧名は'.self::MAX_NAME_LENGTH.'文字以内で入力してください。';
        } elseif (! $this->isNameFree($app, $name, $view)) {
            $errors['name'] = 'この一覧名はすでに使われています。';
        }

        $type = ListViewType::tryFrom(is_string($input['type'] ?? null) ? $input['type'] : '') ?? ListViewType::Table;
        // カスタマイズ形式 comes from the app: a view keeps it, nothing else becomes one
        if ($type === ListViewType::Custom && $view?->type !== ListViewType::Custom) {
            $type = ListViewType::Table;
        }
        $columns = [];
        $dateField = null;
        $titleField = null;

        if ($type === ListViewType::Table) {
            $requested = is_array($input['columns'] ?? null) ? $input['columns'] : [];
            $columns = array_values(array_unique(array_filter($requested, fn (mixed $column): bool => is_string($column) && array_key_exists($column, $schema->columns))));
            if ($columns === []) {
                $errors['columns'] = '表示するフィールドを1つ以上配置してください。';
            }
        } elseif ($type === ListViewType::Calendar) {
            $dateField = is_string($input['dateField'] ?? null) && array_key_exists($input['dateField'], $schema->dateFields()) ? $input['dateField'] : null;
            $titleField = is_string($input['titleField'] ?? null) && array_key_exists($input['titleField'], $schema->titleFields()) ? $input['titleField'] : null;
            if ($dateField === null) {
                $errors['dateField'] = '日付を選択してください。';
            }
            if ($titleField === null) {
                $errors['titleField'] = 'タイトルを選択してください。';
            }
        }

        if ($errors !== []) {
            throw new ListViewInvalid($errors);
        }

        $attributes = [
            'name' => $name,
            'type' => $type,
            'columns' => $type === ListViewType::Table ? $columns : null,
            'date_field' => $dateField,
            'title_field' => $titleField,
            'custom_view' => $type === ListViewType::Custom ? $view->custom_view : null,
            'filter' => RecordFilter::fromArray($input['filter'] ?? [], $schema->filterTypes(), $schema->sortableFields())->toArray(),
        ];

        if ($view !== null) {
            $view->update($attributes);

            return $view;
        }

        return $app->listViews()->create([
            ...$attributes,
            'sort_order' => $this->nextSortOrder($app),
            'created_by' => $userId,
        ]);
    }

    /** 複製: the same settings under 「<一覧名> - コピー」, at the end of the list. */
    public function copy(AppListView $view, ?int $userId): AppListView
    {
        $app = $view->app;
        $name = $view->name;
        do {
            $name = mb_substr($name, 0, self::MAX_NAME_LENGTH - mb_strlen(self::COPY_SUFFIX)).self::COPY_SUFFIX;
        } while (! $this->isNameFree($app, $name));

        return $app->listViews()->create([
            'name' => $name,
            'type' => $view->type,
            'columns' => $view->columns,
            'date_field' => $view->date_field,
            'title_field' => $view->title_field,
            'custom_view' => $view->custom_view,
            'filter' => $view->filter,
            'sort_order' => $this->nextSortOrder($app),
            'created_by' => $userId,
        ]);
    }

    public function delete(AppListView $view): void
    {
        $view->delete();
    }

    /**
     * ドラッグして並び替え: the app's views in the given order (ids of other apps are ignored, missing ones go last).
     *
     * @param  list<int>  $ids
     */
    public function reorder(BusinessApp $app, array $ids): void
    {
        DB::transaction(function () use ($app, $ids): void {
            $views = $app->listViews()->get()->keyBy('id');
            $order = array_values(array_unique([...array_filter($ids, fn (int $id): bool => $views->has($id)), ...$views->keys()->all()]));

            foreach ($order as $index => $id) {
                $views[$id]?->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function isNameFree(BusinessApp $app, string $name, ?AppListView $except = null): bool
    {
        return $name !== ListView::ALL_NAME
            && ! $app->listViews()->where('name', $name)->when($except !== null, fn ($query) => $query->whereKeyNot($except?->id))->exists();
    }

    private function nextSortOrder(BusinessApp $app): int
    {
        return ((int) $app->listViews()->max('sort_order')) + 1;
    }
}
