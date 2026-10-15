<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Concerns\IsOptionList;

/**
 * 役職: the person's company designation (部長, 課長, 主任 …). Optional, one per user; administrators add
 * options from the dropdown or the 役職 screen. Not part of the legacy system (SYSTEM_BRAIN APP_02 has no
 * 役職 field). Grants no system rights.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
final class Position extends Model
{
    use IsOptionList;

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
