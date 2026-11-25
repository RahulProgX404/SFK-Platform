<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Support;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * The user / employee inputs of the HR apps: the legacy ユーザー選択 (any user) and the lookups of 02. 従業員マスタ
 * (employees 在籍中, newest first — the record's own choice stays offered); shown with the ユーザー情報 popup
 * (Core UserPopup::of()).
 */
final class Employees
{
    /** A ユーザー選択 of one user (the legacy field allowed several; one person is meant). */
    public static function user(string $name, string $relation, string $label): Select
    {
        return Select::make($name)->label($label)->relationship($relation, 'name', fn (Builder $query): Builder => $query->orderBy('name'))
            ->searchable()->preload()->placeholder('-----');
    }

    /** A lookup of 02. 従業員マスタ: employees 在籍中, newest first. */
    public static function employee(string $name, string $relation, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship($relation, 'name', fn (Builder $query, ?Model $record): Builder => $query
                ->where(fn (Builder $q): Builder => $q->where('employment_status', EmploymentStatus::Active->value)
                    ->when($record?->getAttribute($name) !== null, fn (Builder $own): Builder => $own->orWhereKey($record?->getAttribute($name))))
                ->orderByDesc('id'))
            ->searchable()
            ->preload()
            ->placeholder('-----');
    }
}
