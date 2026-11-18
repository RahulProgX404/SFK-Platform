<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Modules\Core\Application\Realtime\Realtime;

/**
 * Stands in for Laravel Reverb in the tests: keeps what would have been pushed (Core Realtime), or fails like an
 * unreachable server. `RecordingBroadcaster::install()` switches real time on with it.
 */
final class RecordingBroadcaster extends Broadcaster
{
    /** @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
    public array $pushes = [];

    public bool $fail = false;

    public static function install(): self
    {
        $recorder = new self;
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        Broadcast::extend('reverb', fn () => $recorder);
        Broadcast::purge('reverb');
        Realtime::registerChannels();
        Realtime::reset();

        return $recorder;
    }

    /** @return list<array{channels: list<string>, event: string, payload: array<string, mixed>}> */
    public function to(string $channel, ?string $event = null): array
    {
        return array_values(array_filter($this->pushes, fn (array $push): bool => in_array('private-'.$channel, $push['channels'], true)
            && ($event === null || $push['event'] === $event)));
    }

    public function auth($request): mixed
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    /** @param  array<int, mixed>  $channels */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if ($this->fail) {
            throw new BroadcastException('Reverb unreachable');
        }

        $this->pushes[] = ['channels' => array_map('strval', $this->formatChannels($channels)), 'event' => (string) $event, 'payload' => $payload];
    }
}
