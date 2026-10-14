<?php

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use App\Models\User;

/**
 * Result of creating a user or resetting a password. `$generatedPassword` is the plain temporary
 * password, set only when it was generated, so the administrator can hand it over once; it is never stored.
 */
final readonly class IssuedCredentials
{
    public function __construct(
        public User $user,
        public ?string $generatedPassword = null,
    ) {}
}
