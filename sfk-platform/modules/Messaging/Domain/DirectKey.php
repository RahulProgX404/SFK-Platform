<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

use InvalidArgumentException;

/** The key of the one direct conversation between two users: the same whoever starts it. */
final class DirectKey
{
    public static function for(int $a, int $b): string
    {
        if ($a === $b) {
            throw new InvalidArgumentException('A direct conversation needs two different users.');
        }

        return min($a, $b).':'.max($a, $b);
    }
}
