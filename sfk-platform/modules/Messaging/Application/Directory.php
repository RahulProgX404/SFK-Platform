<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Domain\People\EmploymentStatus;

/**
 * The people of the messenger: every user who can sign in (在籍 / 休職), with what their card shows — name, フリガナ,
 * 役職, 従業員カテゴリ. Read in two queries for everyone and kept in the cache between requests (ReferenceCache: users,
 * 従業員カテゴリ and 役職 bump it when they change), so the inbox, the member lists and the @ suggestions cost no query.
 */
final class Directory
{
    /** @var array<int, array{id: int, name: string, kana: string, position: string, categories: list<string>, category_ids: list<int>, active: bool, email: string, phone: string}>|null */
    private ?array $people = null;

    /** @return array<int, array{id: int, name: string, kana: string, position: string, categories: list<string>, category_ids: list<int>, active: bool, email: string, phone: string}> by user id, signed-in-able or not */
    public function all(): array
    {
        return $this->people ??= ReferenceCache::remember('messaging-directory', self::read(...));
    }

    /** @return array{id: int, name: string, kana: string, position: string, categories: list<string>, category_ids: list<int>, active: bool, email: string, phone: string}|null */
    public function find(int $userId): ?array
    {
        $people = $this->all();
        if (! isset($people[$userId]) && $userId > ($people === [] ? 0 : max(array_keys($people)))) {
            // a user added since the people were read (a long-lived worker, a user created in this request): new
            // users have higher ids, so only those are looked for again
            $this->people = null;
            $people = $this->all();
        }

        return $people[$userId] ?? null;
    }

    public function name(?int $userId): string
    {
        return $userId !== null ? ($this->find($userId)['name'] ?? '退職者') : '';
    }

    public function isActive(int $userId): bool
    {
        return (bool) ($this->find($userId)['active'] ?? false);
    }

    /**
     * Who may be messaged / added to a group: the active people, in フリガナ order.
     *
     * @return list<array{id: int, name: string, kana: string, position: string, categories: list<string>, category_ids: list<int>, active: bool, email: string, phone: string}>
     */
    public function active(): array
    {
        $people = array_values(array_filter($this->all(), fn (array $person): bool => $person['active']));
        usort($people, fn (array $a, array $b): int => [$a['kana'] ?: $a['name'], $a['id']] <=> [$b['kana'] ?: $b['name'], $b['id']]);

        return $people;
    }

    /** @return array<int, array{id: int, name: string, kana: string, position: string, categories: list<string>, category_ids: list<int>, active: bool, email: string, phone: string}> */
    private static function read(): array
    {
        $categories = [];
        foreach (DB::table('employee_category_user')
            ->join('employee_categories', 'employee_categories.id', '=', 'employee_category_user.employee_category_id')
            ->orderBy('employee_categories.sort_order')
            ->orderBy('employee_categories.id')
            ->get(['employee_category_user.user_id', 'employee_categories.id', 'employee_categories.name']) as $row) {
            $categories[(int) $row->user_id]['names'][] = (string) $row->name;
            $categories[(int) $row->user_id]['ids'][] = (int) $row->id;
        }

        $signIn = array_map(fn (EmploymentStatus $status): string => $status->value, array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->canSignIn()));
        $people = [];
        foreach (DB::table('users')
            ->leftJoin('positions', 'positions.id', '=', 'users.position_id')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.last_name_kana', 'users.first_name_kana', 'users.employment_status', 'users.contact_email', 'users.email', 'users.phone', 'positions.name as position']) as $row) {
            $id = (int) $row->id;
            $people[$id] = [
                'id' => $id,
                'name' => (string) $row->name,
                'kana' => trim((string) $row->last_name_kana.' '.(string) $row->first_name_kana),
                'position' => (string) $row->position,
                'categories' => $categories[$id]['names'] ?? [],
                'category_ids' => $categories[$id]['ids'] ?? [],
                'active' => in_array((string) $row->employment_status, $signIn, true),
                'email' => (string) ($row->contact_email ?: $row->email),
                'phone' => (string) $row->phone,
            ];
        }

        return $people;
    }
}
