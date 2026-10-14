<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users;

use App\Models\User;
use Modules\Core\Access\Roles;
use Modules\Core\Domain\People\EmployeeCategory;
use Modules\Core\Domain\People\EmploymentStatus;

/** Display helpers shared by the user table, infolist and widgets. */
final class UserPresenter
{
    public static function statusLabel(mixed $state): string
    {
        $status = $state instanceof EmploymentStatus ? $state : EmploymentStatus::tryFrom((string) $state);

        return $status?->label() ?? '—';
    }

    /** Filament colour per 在籍状況. */
    public static function statusColor(mixed $state): string
    {
        $status = $state instanceof EmploymentStatus ? $state : EmploymentStatus::tryFrom((string) $state);

        return match ($status) {
            EmploymentStatus::Active => 'success',
            EmploymentStatus::OnLeave => 'warning',
            EmploymentStatus::Dismissed => 'danger',
            default => 'gray',
        };
    }

    public static function categoryLabel(mixed $state): string
    {
        $category = $state instanceof EmployeeCategory ? $state : EmployeeCategory::tryFrom((string) $state);

        return $category?->label() ?? '—';
    }

    public static function roleLabel(string $role): string
    {
        return $role === Roles::SUPER_ADMIN ? 'スーパー管理者' : $role;
    }

    public static function fullName(User $user): ?string
    {
        $name = trim(($user->getAttribute('last_name') ?? '').' '.($user->getAttribute('first_name') ?? ''));

        return $name !== '' ? $name : null;
    }

    public static function fullNameKana(User $user): ?string
    {
        $name = trim(($user->getAttribute('last_name_kana') ?? '').' '.($user->getAttribute('first_name_kana') ?? ''));

        return $name !== '' ? $name : null;
    }
}
