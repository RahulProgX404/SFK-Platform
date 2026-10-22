<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rule of the レコードのアクセス権 of an app: the records it covers (a 絞り込む filter — `match` and `conditions` of
 * Modules\Core\Domain\Filters\RecordFilter; none = every record) and its entries in order. Rules are read in
 * `sort_order`; the first rule a record matches decides (Modules\Core\Access\RecordAccess).
 *
 * @property int $id
 * @property int $business_app_id
 * @property int $sort_order
 * @property array<string, mixed>|null $filter
 * @property-read Collection<int, AppRecordRuleEntry> $entries
 */
final class AppRecordRule extends Model
{
    protected $fillable = ['sort_order', 'filter'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'filter' => 'array',
        ];
    }

    /** @return HasMany<AppRecordRuleEntry, $this> in order */
    public function entries(): HasMany
    {
        return $this->hasMany(AppRecordRuleEntry::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }
}
