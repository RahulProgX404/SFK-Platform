<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Cache\OutsideDataCache;
use Modules\Core\Application\Cache\ReferenceCache;

/**
 * One company setting of the messenger (MessagingSettings).
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed>|null $value
 */
final class MessagingSetting extends Model implements OutsideDataCache
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::saved(fn () => ReferenceCache::bump());
        self::deleted(fn () => ReferenceCache::bump());
    }

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
