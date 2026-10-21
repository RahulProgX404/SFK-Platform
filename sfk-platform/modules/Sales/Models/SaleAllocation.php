<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of 按分 (SYSTEM_BRAIN APP_06): the share of a sale credited to a salesperson. 売上金額(按分) = 売上金額 × 割合 / 100
 * (blank while 割合 is blank or 0).
 *
 * @property int $id
 * @property int $sale_id
 * @property int|null $user_id
 * @property string|null $ratio
 * @property-read User|null $user
 * @property-read Sale $sale
 */
final class SaleAllocation extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the sale. */
    public const COLUMNS = ['user_id', 'ratio'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['ratio' => 'decimal:4'];
    }

    /** 売上金額(按分) of a sale amount and a ratio (%), whole yen (truncated). */
    public static function share(int $amount, int|float|string|null $ratio): ?int
    {
        if ($ratio === null || $ratio === '' || (float) $ratio == 0.0) {
            return null;
        }

        return (int) floor($amount * (float) $ratio / 100);
    }

    /** @return BelongsTo<User, $this> 担当者(按分) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
