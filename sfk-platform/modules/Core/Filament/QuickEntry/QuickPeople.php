<?php

declare(strict_types=1);

namespace Modules\Core\Filament\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * The people a quick entry offers (QuickField::person / people / select), like the pickers of the record forms: a
 * ユーザー選択 lists the users by name — without those who left (退職 / 解雇 …), the chosen one always kept — and a lookup of
 * 02. 従業員マスタ the employees 在籍中, newest first (the legacy picker filter).
 */
final class QuickPeople
{
    private const LEFT = [EmploymentStatus::Resigned, EmploymentStatus::Retired, EmploymentStatus::Dismissed];

    /** @return array<int, string> id => name */
    public static function users(?int $keep = null): array
    {
        $left = array_map(fn (EmploymentStatus $status): string => $status->value, self::LEFT);

        return self::names(User::query()
            ->where(fn (Builder $query): Builder => $query->whereNull('employment_status')->orWhereNotIn('employment_status', $left)
                ->when($keep !== null, fn (Builder $own): Builder => $own->orWhereKey($keep)))
            ->orderBy('name'));
    }

    /** @return array<int, string> id => name */
    public static function employees(?int $keep = null): array
    {
        return self::names(User::query()
            ->where(fn (Builder $query): Builder => $query->where('employment_status', EmploymentStatus::Active->value)
                ->when($keep !== null, fn (Builder $own): Builder => $own->orWhereKey($keep)))
            ->orderByDesc('id'));
    }

    /**
     * @param  Builder<User>  $query
     * @return array<int, string>
     */
    private static function names(Builder $query): array
    {
        return $query->pluck('name', 'id')->mapWithKeys(fn (mixed $name, int|string $id): array => [(int) $id => (string) $name])->all();
    }
}
