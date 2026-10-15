<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users;

use App\Models\User;
use Modules\Core\Access\UserType;
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

    /** 作業単価 / 残業単価: integer yen per hour. */
    public static function hourlyRate(mixed $state): string
    {
        return is_numeric($state) ? number_format((int) $state).' 円/時' : '—';
    }

    public static function typeLabel(mixed $state): string
    {
        $type = $state instanceof UserType ? $state : UserType::tryFrom((string) $state);

        return $type?->label() ?? '—';
    }

    /** Filament colour per ユーザー種別. */
    public static function typeColor(mixed $state): string
    {
        $type = $state instanceof UserType ? $state : UserType::tryFrom((string) $state);

        return match ($type) {
            UserType::SuperAdmin => 'danger',
            UserType::Admin => 'warning',
            default => 'gray',
        };
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
