<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use App\Models\User;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * Users as choices (id => name, by name), read through the ReferenceCache — a saved user bumps it — instead of a query
 * on every 絞り込む and every record page (performance pass 2026-11-20: one list was read three times per 絞り込む).
 */
final class UserChoices
{
    /** @return array<int|string, string> every user (the ユーザー conditions of 絞り込む) */
    public static function all(): array
    {
        return ReferenceCache::remember('user-choices:all', fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    /** @return array<int|string, string> the users who can sign in (宛先 of コメント) */
    public static function active(): array
    {
        return ReferenceCache::remember('user-choices:active', fn (): array => User::query()
            ->whereIn('employment_status', array_map(
                fn (EmploymentStatus $status): string => $status->value,
                array_values(array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->canSignIn())),
            ))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all());
    }
}
