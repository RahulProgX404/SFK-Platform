<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * スペース: a group inside one dashboard (`panel_id`) holding member users and apps. A user may belong to
 * several spaces, and so may an app (of this dashboard or others). Employees use an app as members of one of its
 * spaces.
 *
 * @property int $id
 * @property string $panel_id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 */
final class Space extends Model
{
    protected $fillable = ['panel_id', 'name', 'description', 'sort_order'];

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return BelongsToMany<BusinessApp, $this> */
    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(BusinessApp::class)->withTimestamps()->orderBy('business_apps.sort_order')->orderBy('business_apps.name');
    }
}
