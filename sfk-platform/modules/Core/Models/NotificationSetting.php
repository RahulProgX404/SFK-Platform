<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's choice for one kind of 通知 (プロフィール → 通知設定): 受け取る (bell) and メール. Without a row the user
 * receives the kind in the bell only.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind NotificationKind::$key
 * @property bool $receive
 * @property bool $mail
 * @property-read User $user
 */
final class NotificationSetting extends Model
{
    protected $fillable = ['user_id', 'kind', 'receive', 'mail'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'receive' => 'boolean',
            'mail' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
