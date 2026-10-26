<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Records chosen on a list for a 出力設定 (the filtered list or the checked rows), kept for a while under a random
 * token so the browser can open the 帳票 in a new tab (GET /documents/print/<token>). Only the user who chose them
 * can use a token; the records are checked again when the file is made.
 */
final class PrintJobs
{
    private const TTL_MINUTES = 30;

    /** @param  list<int|string>  $keys  the records' keys, in list order */
    public function store(int $userId, int $outputId, array $keys): string
    {
        $token = Str::random(40);
        Cache::put(self::key($token), ['user' => $userId, 'output' => $outputId, 'keys' => $keys], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /** @return array{user: int, output: int, keys: list<int|string>}|null */
    public function find(string $token): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        $job = Cache::get(self::key($token));

        return is_array($job) && isset($job['user'], $job['output'], $job['keys']) ? $job : null;
    }

    private static function key(string $token): string
    {
        return 'core:documents:print:'.$token;
    }
}
