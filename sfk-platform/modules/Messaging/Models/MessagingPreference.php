<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Messaging\Domain\Availability;

/**
 * A user's メッセージ設定 (no row = the defaults).
 *
 * @property int $id
 * @property int $user_id
 * @property bool $sound
 * @property bool $desktop
 * @property bool $preview
 * @property bool $presence
 * @property Carbon|null $dnd_until
 * @property Availability $status
 */
final class MessagingPreference extends Model implements OutsideDataCache
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'sound' => true,
        'desktop' => false,
        'preview' => false,
        'presence' => true,
        'status' => 'auto',
    ];

    /** おやすみモード (for a while) or 取り込み中 (until switched back): no sound, toasts or browser notifications. */
    public function inDoNotDisturb(): bool
    {
        return ($this->dnd_until !== null && $this->dnd_until->isFuture()) || $this->status === Availability::Dnd;
    }

    protected function casts(): array
    {
        return [
            'sound' => 'boolean',
            'desktop' => 'boolean',
            'preview' => 'boolean',
            'presence' => 'boolean',
            'dnd_until' => 'datetime',
            'status' => Availability::class,
        ];
    }
}
