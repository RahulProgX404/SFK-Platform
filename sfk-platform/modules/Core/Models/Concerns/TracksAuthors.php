<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 作成者 / 更新者 of a record (`created_by` / `updated_by`): set from the signed-in user on save, shown with the ユーザー情報
 * popup; also the recipients of its コメント (the usual legacy app notification on 「コメントの書き込み」 → 作成者 /
 * 更新者).
 *
 * @mixin Model
 *
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::saving(function (Model $record): void {
            $userId = auth()->id();
            if ($userId !== null) {
                $record->setAttribute('updated_by', (int) $userId);
                if (! $record->exists) {
                    $record->setAttribute('created_by', (int) $userId);
                }
            }
        });
    }

    /** @return list<User|null> 作成者 and 更新者 */
    public function commentRecipients(): iterable
    {
        return [$this->creator, $this->updater];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
