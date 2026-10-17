<?php

declare(strict_types=1);

namespace Modules\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;

/**
 * 機械 (SYSTEM_BRAIN APP_03): a machine or process with its seven cost rates (unit prices in yen with up to 4
 * decimals, kept as decimal strings — see Core UnitPrice). Daily reports, the bag-making schedule and accident
 * reports will reference `id` (not the name, unlike the legacy lookups) and snapshot the rates they use.
 * コメント notify 作成者 and 更新者 (legacy app notification on 「コメントの書き込み」, APP_03 permissions.md).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string|null $code
 * @property string $name
 * @property string|null $processing_rate 加工単価(枚)
 * @property string|null $changeover_rate 型替単価(回)
 * @property string|null $handwork_rate 内職単価(H)
 * @property string|null $machine_rate 機械単価(H)
 * @property string|null $printing_rate 印刷単価
 * @property string|null $plate_change_rate 版替単価
 * @property string|null $ink_mixing_rate 練インキ単価
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
final class Machine extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments;
    use RecordsHistory;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'processing_rate' => 'decimal:4',
            'changeover_rate' => 'decimal:4',
            'handwork_rate' => 'decimal:4',
            'machine_rate' => 'decimal:4',
            'printing_rate' => 'decimal:4',
            'plate_change_rate' => 'decimal:4',
            'ink_mixing_rate' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $machine): void {
            $userId = auth()->id();
            if ($userId !== null) {
                $machine->updated_by = (int) $userId;
                if (! $machine->exists) {
                    $machine->created_by = (int) $userId;
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
