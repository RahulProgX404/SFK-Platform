<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use WeakMap;

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

    /** @var WeakMap<Request, array<int, array<int, true>>>|null user id => ids of their spaces, per request */
    private static ?WeakMap $memberships = null;

    /** Whether the user is a member; the user's spaces are read once per request (sidebar, app permissions). */
    public function hasMember(User $user): bool
    {
        self::$memberships ??= new WeakMap;
        $request = request();
        $cached = self::$memberships[$request] ?? [];

        if (! array_key_exists($user->id, $cached)) {
            $ids = $user->spaces()->pluck('spaces.id')->all();
            $cached[$user->id] = array_fill_keys(array_map('intval', $ids), true);
            self::$memberships[$request] = $cached;
        }

        return isset($cached[$user->id][$this->id]);
    }

    /** Drops the memos of the spaces (members here, the apps' spaces in BusinessApp::forCode()). */
    public static function forgetCached(): void
    {
        self::$memberships = null;
        BusinessApp::forgetCached();
    }

    protected static function booted(): void
    {
        self::saved(function (): void {
            self::forgetCached();
        });
        self::deleted(function (): void {
            self::forgetCached();
        });
    }

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
