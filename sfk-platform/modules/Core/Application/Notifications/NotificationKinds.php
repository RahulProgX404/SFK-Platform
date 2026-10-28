<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use InvalidArgumentException;

/**
 * The kinds of 通知 of the enabled modules, in the order they were registered (Core first, then the modules in
 * dependency order). A module registers its kinds in its service provider's bootModule().
 */
final class NotificationKinds
{
    /** @var array<string, NotificationKind> */
    private array $kinds = [];

    public function register(NotificationKind $kind): void
    {
        $this->kinds[$kind->key] = $kind;
    }

    public function get(string $key): NotificationKind
    {
        return $this->kinds[$key] ?? throw new InvalidArgumentException("Unknown notification kind [{$key}]: register it with NotificationKinds::register().");
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->kinds);
    }

    /** @return list<NotificationKind> */
    public function all(): array
    {
        return array_values($this->kinds);
    }
}
