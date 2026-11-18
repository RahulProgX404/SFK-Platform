<?php

declare(strict_types=1);

namespace Modules\Core\Application\Realtime;

use Closure;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pushes to the open pages over WebSockets (Laravel Reverb, docs/realtime.md) — the 通知 of the bell and the changes of
 * 社内メッセージ. Every push is on a private channel the page subscribed after the app checked it may
 * (routes/channels in the modules' providers): `sfk.user.<id>` (the user's own: 通知, their inbox) and the modules' own
 * (e.g. `sfk.conversation.<id>`, members only). Pushes carry signals and small payloads; what a user sees is still
 * read through the authorized API.
 *
 * Sent after the transaction commits (a rolled-back change is never announced) and never at the cost of the request:
 * when Reverb cannot be reached the error is reported, pushes pause for 30 seconds, and the pages keep their polling
 * fallback (Core NotificationPulse, Messaging Signals), which stays in place.
 */
final class Realtime
{
    /** When a push last failed: the next ones wait a little (a queue worker or a request would hang on each). */
    private static float $failedAt = 0.0;

    private const RETRY_SECONDS = 30;

    /** @var array<string, Closure> channel pattern => who may subscribe (the modules' rules) */
    private static array $channels = [];

    /**
     * Declares a private channel and who may subscribe to it (asked by POST broadcasting/auth), e.g.
     * `Realtime::channel('sfk.user.{id}', fn (User $user, $id) => …)`. Kept here as well, so the rules follow a
     * broadcaster created later (registerChannels()).
     */
    public static function channel(string $pattern, Closure $rule): void
    {
        self::$channels[$pattern] = $rule;
        Broadcast::channel($pattern, $rule);
    }

    /** Gives the declared channels to the current broadcaster (after its connection changed, e.g. in tests). */
    public static function registerChannels(): void
    {
        foreach (self::$channels as $pattern => $rule) {
            Broadcast::channel($pattern, $rule);
        }
    }

    /** Whether pushes go out (BROADCAST_CONNECTION=reverb with an app key). */
    public static function enabled(): bool
    {
        $default = (string) config('broadcasting.default');

        return in_array($default, ['reverb', 'pusher'], true) && filled(config("broadcasting.connections.{$default}.key"));
    }

    /**
     * @param  list<string>  $channels  private channel names without the "private-" prefix, e.g. "sfk.user.5"
     * @param  array<string, mixed>  $payload
     */
    public static function send(array $channels, string $event, array $payload = []): void
    {
        if ($channels === [] || ! self::enabled()) {
            return;
        }

        DB::afterCommit(function () use ($channels, $event, $payload): void {
            if (microtime(true) - self::$failedAt < self::RETRY_SECONDS) {
                return;
            }

            try {
                Broadcast::connection()->broadcast(
                    array_map(fn (string $channel): PrivateChannel => new PrivateChannel($channel), array_values(array_unique($channels))),
                    $event,
                    $payload,
                );
            } catch (Throwable $exception) {
                self::$failedAt = microtime(true);
                report($exception);
            }
        });
    }

    /** The channel of one user (their 通知 and inbox). */
    public static function userChannel(int $userId): string
    {
        return 'sfk.user.'.$userId;
    }

    /**
     * Where the browser connects, for <meta name="sfk-realtime">: the app key and the public address (empty host = the
     * page's own origin, where nginx forwards /app to Reverb). Null when real time is off.
     *
     * @return array{key: string, host: string|null, port: int|null, scheme: string|null}|null
     */
    public static function clientConfig(): ?array
    {
        if (! self::enabled()) {
            return null;
        }
        $default = (string) config('broadcasting.default');

        return [
            'key' => (string) config("broadcasting.connections.{$default}.key"),
            'host' => filled(config('broadcasting.public.host')) ? (string) config('broadcasting.public.host') : null,
            'port' => filled(config('broadcasting.public.port')) ? (int) config('broadcasting.public.port') : null,
            'scheme' => filled(config('broadcasting.public.scheme')) ? (string) config('broadcasting.public.scheme') : null,
        ];
    }

    /**
     * The WebSocket origins for the Content-Security-Policy's connect-src: the configured public address, else the
     * page's own host over ws / wss (CSP 'self' does not cover WebSockets in every browser).
     */
    public static function cspOrigins(?string $requestHost, bool $secure): string
    {
        $config = self::clientConfig();
        if ($config === null) {
            return '';
        }
        if ($config['host'] !== null) {
            $scheme = ($config['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';

            return ' '.$scheme.'://'.$config['host'].($config['port'] !== null ? ':'.$config['port'] : '');
        }

        return $requestHost !== null && $requestHost !== '' ? ' '.($secure ? 'wss' : 'ws').'://'.$requestHost : '';
    }

    /** Forgets a failed connection (tests). */
    public static function reset(): void
    {
        self::$failedAt = 0.0;
    }
}
