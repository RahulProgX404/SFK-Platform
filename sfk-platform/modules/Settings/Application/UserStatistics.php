<?php

declare(strict_types=1);

namespace Modules\Settings\Application;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * Figures for the user management and settings dashboards. Always derived from the users rows
 * (no stored counters).
 */
final class UserStatistics
{
    public function total(): int
    {
        return User::query()->count();
    }

    public function withStatus(EmploymentStatus $status): int
    {
        return User::query()->where('employment_status', $status->value)->count();
    }

    public function registeredSince(Carbon $since): int
    {
        return User::query()->where('created_at', '>=', $since)->count();
    }

    /** @return list<int> registrations per calendar month, oldest first, ending with the current month */
    public function monthlyRegistrations(int $months = 6): array
    {
        $result = [];
        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($offset);
            $result[] = User::query()
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        }

        return $result;
    }

    public function pendingPasswordChange(): int
    {
        return User::query()->where('must_change_password', true)->count();
    }
}
