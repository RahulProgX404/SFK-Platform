<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 組織 (company department / section). Organizations can be nested (部 → 課) and define their own
 * positions (役職). `code` corresponds to 部課コード in the kintone employee master (APP_02).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
final class Organization extends Model
{
    protected $fillable = ['parent_id', 'code', 'name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Organization, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('sort_order')->orderByDesc('rank');
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return BelongsToMany<User, $this, OrganizationMembership> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationMembership::class)
            ->withPivot(['id', 'position_id', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Ids of every organization below this one, used to keep the hierarchy free of cycles.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $level = [$this->id];

        while ($level !== []) {
            $level = self::query()->whereIn('parent_id', $level)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return array_values(array_map('intval', $ids));
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
