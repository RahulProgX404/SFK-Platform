<?php

declare(strict_types=1);

namespace Modules\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\MasterData\Domain\Consumables\StockMovementReason;

/**
 * One movement of a consumable's stock (SYSTEM_BRAIN APP_19 stock-movement ledger): + for 入庫, − for 使用, either
 * sign for 棚卸調整. `source` is the record that caused it (e.g. a daily report row, 08N 使用数), null when entered by
 * hand.
 *
 * @property int $id
 * @property int $consumable_id
 * @property Carbon $moved_on
 * @property StockMovementReason $reason
 * @property string $quantity
 * @property string|null $note
 * @property int|null $created_by
 * @property-read Consumable $consumable
 * @property-read User|null $creator
 */
final class ConsumableStockMovement extends Model
{
    protected $guarded = ['id', 'created_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'moved_on' => 'date',
            'reason' => StockMovementReason::class,
            'quantity' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $movement): void {
            $userId = auth()->id();
            if ($userId !== null) {
                $movement->created_by = (int) $userId;
            }
        });
    }

    /** @return BelongsTo<Consumable, $this> */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
