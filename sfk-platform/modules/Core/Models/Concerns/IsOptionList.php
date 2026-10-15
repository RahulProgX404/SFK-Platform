<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A user-maintained dropdown list (`name`, `sort_order`, `is_active`), e.g. 従業員カテゴリ and 役職.
 * Inactive options stay on the records that use them but are no longer offered for selection.
 */
trait IsOptionList
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Options for a select: active options plus the currently selected one(s).
     *
     * @return array<int, string> id => name
     */
    public static function options(mixed $selected = null): array
    {
        return static::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when(
                filled($selected),
                fn (Builder $query) => $query->orWhereIn('id', (array) $selected),
            ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    /** Adds an option at the end of the list (used by the dropdown's 「選択肢を追加」). */
    public static function createAtEnd(string $name, bool $active = true): static
    {
        return static::query()->create([
            'name' => trim($name),
            'sort_order' => ((int) static::query()->max('sort_order')) + 1,
            'is_active' => $active,
        ]);
    }
}
