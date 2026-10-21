<?php

declare(strict_types=1);

namespace Modules\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\MasterData\Domain\Consumables\BoxType;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Domain\Consumables\ConsumableKind;

/**
 * 消耗品 (SYSTEM_BRAIN APP_19): a consumable or material (cardboard boxes, paper, goods) with prices in yen (decimal
 * strings, Core UnitPrice), lot size and reorder point. 在庫数 is derived from the stock movements (withStock(),
 * stock()); 発注判定 follows from it. Daily reports (08N) and consumables orders (20) will reference `id`.
 * コメント notify 作成者 and 更新者 (legacy app notification, APP_19 permissions.md).
 *
 * @property int $id
 * @property int|null $legacy_id
 * @property string $name
 * @property BoxType|null $box_type
 * @property ConsumableKind|null $kind
 * @property string|null $reorder_point
 * @property string|null $amount
 * @property string|null $unit_price
 * @property string|null $unit
 * @property string|null $lot_size
 * @property int|null $supplier_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Partner|null $supplier
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
final class Consumable extends Model implements HasRecordComments, HasRecordHistory
{
    use RecordsComments;
    use RecordsHistory;

    protected $guarded = ['id', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'box_type' => BoxType::class,
            'kind' => ConsumableKind::class,
            'reorder_point' => 'decimal:4',
            'amount' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'lot_size' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $consumable): void {
            $userId = auth()->id();
            if ($userId !== null) {
                $consumable->updated_by = (int) $userId;
                if (! $consumable->exists) {
                    $consumable->created_by = (int) $userId;
                }
            }
        });
    }

    /** Subquery of 在庫数: the sum of the movements, null without any. */
    public static function stockQuery(): QueryBuilder
    {
        return ConsumableStockMovement::query()
            ->selectRaw('sum(consumable_stock_movements.quantity)')
            ->whereColumn('consumable_stock_movements.consumable_id', 'consumables.id')
            ->toBase();
    }

    /**
     * Adds `stock` (在庫数) to the selected columns.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStock(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('consumables.*');
        }

        return $query->selectSub(self::stockQuery(), 'stock');
    }

    /** 在庫数 (4 decimals), null while nothing was moved. */
    public function stock(): ?string
    {
        $stock = match (true) {
            array_key_exists('stock', $this->getAttributes()) => $this->getAttributes()['stock'],
            $this->stockMovements()->exists() => $this->stockMovements()->sum('quantity'),
            default => null,
        };

        return is_numeric($stock) ? number_format((float) $stock, 4, '.', '') : null;
    }

    /** 発注判定 */
    public function reorderFlag(): string
    {
        return ConsumableFields::reorderFlag($this->stock(), $this->reorder_point);
    }

    /**
     * The snapshot is the record's own fields; the stock lives in the movements (not restored).
     *
     * @return list<string>
     */
    protected function historyExcludedColumns(): array
    {
        return [$this->getKeyName(), 'created_by', 'updated_by', 'stock', $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()];
    }

    /** @return list<User|null> 作成者 and 更新者 */
    public function commentRecipients(): iterable
    {
        return [$this->creator, $this->updater];
    }

    /** @return BelongsTo<Partner, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    /** @return HasMany<ConsumableStockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ConsumableStockMovement::class);
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
