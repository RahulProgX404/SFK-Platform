<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Modules\Core\Application\Cache\ReferenceCache;
use WeakMap;

/**
 * スペース: a group inside one dashboard (`panel_id`) holding member users and apps. A user may belong to
 * several spaces, and so may an app (of this dashboard or others). Employees use an app as members of one of its
 * spaces. Members are added by name (`space_user.direct`) or by 従業員カテゴリ (`employeeCategories()`: every employee
 * of the category, now and later — SpaceMembership keeps `users()` the list of all of them).
 *
 * Every space has one permission setting (スペースの権限, `permissions()`: rows for 全員 / 従業員カテゴリ / 個人, none
 * when it is created): its members get those abilities in every app of the space, added to the apps' own
 * permissions — and an app in several of the user's spaces gets the abilities of all of them (AppAccess).
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

    /** @var WeakMap<Request, array<string, Collection<int, SpacePermission>>>|null "userId:spaceId" => rows, per request */
    private static ?WeakMap $permissionRows = null;

    /**
     * The rows of the space's permission setting that apply to the user (全員, their own, their 従業員カテゴリ'), read
     * once per request and space: the sidebar asks for every app of the space. Saving a row drops the memo.
     *
     * @param  list<int>  $categoryIds
     * @return Collection<int, SpacePermission>
     */
    public function permissionRowsFor(User $user, array $categoryIds): Collection
    {
        self::$permissionRows ??= new WeakMap;
        $request = request();
        $cached = self::$permissionRows[$request] ?? [];
        $key = $user->id.':'.$this->id;

        if (! array_key_exists($key, $cached)) {
            $cached[$key] = ReferenceCache::rememberModels('space-rows:'.$key.':'.implode(',', $categoryIds), SpacePermission::class,
                fn () => $this->permissions()->applyingTo($user, $categoryIds)->get());
            self::$permissionRows[$request] = $cached;
        }

        return $cached[$key];
    }

    /** Whether the user is a member; the user's spaces are read once per request (sidebar, app permissions). */
    public function hasMember(User $user): bool
    {
        self::$memberships ??= new WeakMap;
        $request = request();
        $cached = self::$memberships[$request] ?? [];

        if (! array_key_exists($user->id, $cached)) {
            $ids = ReferenceCache::remember('space-ids:'.$user->id, fn (): array => $user->spaces()->pluck('spaces.id')->all());
            $cached[$user->id] = array_fill_keys(array_map('intval', $ids), true);
            self::$memberships[$request] = $cached;
        }

        return isset($cached[$user->id][$this->id]);
    }

    /** Drops the memos of the spaces (members and permission rows here, the apps' spaces in BusinessApp::forCode()). */
    public static function forgetCached(): void
    {
        self::$memberships = null;
        self::$permissionRows = null;
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

    /** @return BelongsToMany<User, $this> every member; pivot `direct`: added by name (not only through a 従業員カテゴリ) */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('direct')->withTimestamps();
    }

    /** @return BelongsToMany<EmployeeCategory, $this> the 従業員カテゴリ whose employees are members */
    public function employeeCategories(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeCategory::class)->withTimestamps()->orderBy('employee_categories.sort_order')->orderBy('employee_categories.id');
    }

    /** @return HasMany<SpacePermission, $this> スペースの権限 (rows in the order 個人, 従業員カテゴリ, 全員) */
    public function permissions(): HasMany
    {
        return $this->hasMany(SpacePermission::class)
            ->orderByRaw("case subject_type when 'user' then 1 when 'category' then 2 else 3 end")
            ->orderBy('id');
    }

    /** @return BelongsToMany<BusinessApp, $this> */
    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(BusinessApp::class)->withTimestamps()->orderBy('business_apps.sort_order')->orderBy('business_apps.name');
    }
}
