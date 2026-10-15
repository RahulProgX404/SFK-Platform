<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Concerns\IsOptionList;

/**
 * 従業員カテゴリ (SYSTEM_BRAIN APP_02): the person's position/duty in the company. Seeded with the legacy
 * options; administrators can add more. `dashboards` lists the dashboards (panel ids) its users may open —
 * see DashboardAccess. Inactive categories stay on their users (and keep granting their dashboards) but are
 * no longer offered for selection.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property list<string>|null $dashboards
 */
final class EmployeeCategory extends Model
{
    use IsOptionList;

    protected $fillable = ['name', 'sort_order', 'is_active', 'dashboards'];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'dashboards' => 'array',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
