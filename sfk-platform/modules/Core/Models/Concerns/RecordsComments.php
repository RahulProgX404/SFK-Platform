<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\RecordComment;

/**
 * Default コメント behaviour of a record (Modules\Core\Application\Comments\HasRecordComments): nobody but the 宛先
 * is notified, and a deleted record takes its comments with it.
 *
 * @mixin Model
 */
trait RecordsComments
{
    public static function bootRecordsComments(): void
    {
        static::deleted(function (Model $record): void {
            RecordComment::query()->where('record_type', $record->getMorphClass())->where('record_id', $record->getKey())->delete();
        });
    }

    /** @return iterable<User|null> */
    public function commentRecipients(): iterable
    {
        return [];
    }
}
