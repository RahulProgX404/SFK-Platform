<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Core\Models\Space;

/**
 * One user base for the whole platform. Which dashboards (panels) a user may open is decided by their own
 * `dashboards`, their 従業員カテゴリ and their ユーザー種別 (`user_type`) — see DashboardAccess. What a user may do in the system
 * is their ユーザー種別; their duty in the company is 従業員カテゴリ, their designation 役職 (optional).
 * `user_type` is not mass assignable: it is set by UserAccountService / SuperAdminProvisioner after
 * the authorization checks. The employee profile (氏名, 在籍状況, 役職 …)
 * lives on the same record (SYSTEM_BRAIN APP_02 "Employee ↔ system user link"). The employee-master
 * codes, category and hourly rates are administrator-only; the rates are confidential and hidden from
 * serialization — see {@see EmployeeDataAccess}.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserType $user_type
 * @property EmploymentStatus $employment_status
 * @property Gender|null $gender
 * @property int|null $position_id
 * @property list<string>|null $dashboards dashboards granted to this user on top of their 従業員カテゴリ
 * @property string|null $sales_staff_code
 * @property string|null $worker_code
 * @property string|null $department_code
 * @property int|null $work_rate
 * @property int|null $overtime_rate
 * @property bool $must_change_password
 * @property bool $managed_by_env
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_changed_at
 * @property int|null $list_page_size 表示件数 of the app lists (blank = 100)
 */
#[Fillable([
    'name', 'email', 'password',
    'last_name', 'first_name', 'last_name_kana', 'first_name_kana',
    'employee_code', 'employment_status',
    'sales_staff_code', 'worker_code', 'department_code', 'work_rate', 'overtime_rate',
    'position_id', 'dashboards', 'gender',
    'birthday', 'hired_on', 'left_on',
    'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
    'emergency_contact_name', 'emergency_contact_phone', 'notes',
])]
#[Hidden(['password', 'remember_token', 'work_rate', 'overtime_rate'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'user_type' => 'employee',
        'employment_status' => 'active',
        'must_change_password' => false,
        'managed_by_env' => false,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return DashboardAccess::allows($this, $panel);
    }

    /**
     * Dashboard (panel) ids granted to the user directly or through any of their 従業員カテゴリ, read fresh
     * from the database (DashboardAccess memoizes it per request).
     *
     * @return list<string>
     */
    public function grantedDashboards(): array
    {
        $own = DB::table('users')->where('id', $this->id)->value('dashboards');
        $categories = DB::table('employee_category_user')
            ->join('employee_categories', 'employee_categories.id', '=', 'employee_category_user.employee_category_id')
            ->where('employee_category_user.user_id', $this->id)
            ->pluck('employee_categories.dashboards')
            ->all();

        $ids = [];
        foreach ([$own, ...$categories] as $json) {
            foreach ((array) json_decode((string) $json, true) as $id) {
                $ids[] = (string) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === UserType::SuperAdmin;
    }

    /** Whether this user may create, edit, reset and delete the given user (ユーザー種別 hierarchy). */
    public function canManageUser(self $user): bool
    {
        return ! $user->managed_by_env && $this->user_type->canManage($user->user_type);
    }

    /**
     * 従業員カテゴリ (several allowed).
     *
     * @return BelongsToMany<EmployeeCategory, $this>
     */
    public function employeeCategories(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeCategory::class)->withTimestamps()->orderBy('sort_order');
    }

    /** @return BelongsToMany<Space, $this> */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class)->withTimestamps();
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserType::class,
            'employment_status' => EmploymentStatus::class,
            'dashboards' => 'array',
            'gender' => Gender::class,
            'work_rate' => 'integer',
            'overtime_rate' => 'integer',
            'birthday' => 'date',
            'hired_on' => 'date',
            'left_on' => 'date',
            'must_change_password' => 'boolean',
            'managed_by_env' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
