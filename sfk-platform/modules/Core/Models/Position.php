<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 役職 defined within one organization. `rank` orders seniority (higher = more senior).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $code
 * @property int $rank
 * @property int $sort_order
 */
final class Position extends Model
{
    protected $fillable = ['organization_id', 'name', 'code', 'rank', 'sort_order'];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }
}
