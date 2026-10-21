<?php

declare(strict_types=1);

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of チェックリスト (SYSTEM_BRAIN APP_17): No, 項目, 評価 (はい / いいえ, default はい), チェック (✓) and 備考.
 *
 * @property int $id
 * @property int $safety_check_id
 * @property string|null $number
 * @property string|null $item
 * @property string $answer
 * @property bool $checked
 * @property string|null $notes
 * @property-read SafetyCheck $check
 */
final class SafetyCheckItem extends Model
{
    /** Columns of a row, as kept in the 変更履歴 of the checklist. */
    public const COLUMNS = ['number', 'item', 'answer', 'checked', 'notes'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['answer' => 'はい'];

    protected function casts(): array
    {
        return [
            'number' => 'decimal:2',
            'checked' => 'boolean',
        ];
    }

    /** @return BelongsTo<SafetyCheck, $this> */
    public function check(): BelongsTo
    {
        return $this->belongsTo(SafetyCheck::class, 'safety_check_id');
    }
}
