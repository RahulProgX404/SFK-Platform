<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in one organization (所属), with an optional position of that organization.
 * `is_primary` marks the main organization (主所属) when a user belongs to several.
 *
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property int|null $position_id
 * @property bool $is_primary
 */
final class OrganizationMembership extends Pivot
{
    protected $table = 'organization_user';

    public $incrementing = true;

    protected $fillable = ['user_id', 'organization_id', 'position_id', 'is_primary'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
