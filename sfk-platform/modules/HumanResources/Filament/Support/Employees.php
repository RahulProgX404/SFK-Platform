<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Support;

use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Support\UserPopup;

/**
 * The user / employee inputs of the HR apps: the legacy ユーザー選択 (any user) and the lookups of 02. 従業員マスタ
 * (employees 在籍中, newest first — the record's own choice stays offered), shown with the ユーザー情報 popup.
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

    /** The ユーザー情報 popup of a related user of the record. */
    public static function popup(string $relation): Closure
    {
        return fn (Model $record): HtmlString => UserPopup::html($record->getRelationValue($relation) instanceof User ? $record->getRelationValue($relation) : null);
    }
}
