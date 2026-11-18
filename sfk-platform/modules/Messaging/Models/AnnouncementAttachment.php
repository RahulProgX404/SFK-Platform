<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Application\Cache\OutsideDataCache;

/**
 * A file of a 全社アナウンス.
 *
 * @property int $id
 * @property int $announcement_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property-read Announcement|null $announcement
 */
final class AnnouncementAttachment extends Model implements OutsideDataCache
{
    protected $table = 'announcement_attachments';

    protected $guarded = ['id'];

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
