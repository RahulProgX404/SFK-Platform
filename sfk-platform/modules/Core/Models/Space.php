<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * スペース: a group inside one dashboard (`panel_id`) holding member users and apps. A user may belong to
 * several spaces; an app to at most one. Employees use an app only as members of its space.
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

    /** @return HasMany<BusinessApp, $this> */
    public function apps(): HasMany
    {
        return $this->hasMany(BusinessApp::class)->orderBy('sort_order')->orderBy('name');
    }
}
