<?php

declare(strict_types=1);

namespace Modules\Settings\Application;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * Figures for the user management and settings dashboards. Always derived from the users rows
 * (no stored counters). Totals, status counts and pending password changes come from a single grouped
 * query, memoized on the instance; monthly registrations from one more.
 */
final class UserStatistics
{
    /** @var array{total: int, pending: int, by_status: array<string, int>}|null */
    private ?array $summary = null;

    public function total(): int
    {
        return $this->summary()['total'];
    }

    public function withStatus(EmploymentStatus $status): int
    {
        return $this->summary()['by_status'][$status->value] ?? 0;
    }

    public function pendingPasswordChange(): int
    {
        return $this->summary()['pending'];
    }

    public function registeredSince(Carbon $since): int
    {
        return User::query()->where('created_at', '>=', $since)->count();
    }

    /** @return list<int> registrations per calendar month, oldest first, ending with the current month */
    public function monthlyRegistrations(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        $perMonth = [];
        for ($offset = 0; $offset < $months; $offset++) {
            $perMonth[$start->copy()->addMonthsNoOverflow($offset)->format('Y-m')] = 0;
        }

        foreach (User::query()->where('created_at', '>=', $start)->pluck('created_at') as $createdAt) {
            $month = Carbon::parse($createdAt)->format('Y-m');
            if (array_key_exists($month, $perMonth)) {
                $perMonth[$month]++;
            }
        }

        return array_values($perMonth);
    }

    /** @return array{total: int, pending: int, by_status: array<string, int>} */
    private function summary(): array
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        $summary = ['total' => 0, 'pending' => 0, 'by_status' => []];

        $rows = User::query()
            ->toBase()
            ->selectRaw('employment_status, count(*) as total, sum(case when must_change_password then 1 else 0 end) as pending')
            ->groupBy('employment_status')
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $summary['total'] += $count;
            $summary['pending'] += (int) $row->pending;
            $summary['by_status'][(string) $row->employment_status] = $count;
        }

        return $this->summary = $summary;
    }
}
