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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Comments\HasRecordComments;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Models\Concerns\RecordsComments;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\NotificationSetting;
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
 * serialization — see {@see EmployeeDataAccess}. The user is also the record of the app 02. 従業員マスタ and takes its
 * コメント (only the 宛先 are notified: users have no 作成者 / 更新者).
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
 * @property bool $show_financial_analysis 財務分析を表示 (set by administrators)
 * @property string $financial_scope 財務分析の範囲: overall (全体) | personal (個人のみ) — with 財務分析を表示
 * @property list<int>|null $app_order the user's own order of the apps in the アプリ panel (app ids)
 */
#[Fillable([
    'name', 'email', 'password',
    'last_name', 'first_name', 'last_name_kana', 'first_name_kana',
    'employee_code', 'employment_status',
    'sales_staff_code', 'worker_code', 'department_code', 'work_rate', 'overtime_rate',
    'position_id', 'dashboards', 'gender',
    'birthday', 'hired_on', 'left_on',
    'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
    'emergency_contact_name', 'emergency_contact_phone', 'notes', 'show_financial_analysis', 'financial_scope',
])]
#[Hidden(['password', 'remember_token', 'work_rate', 'overtime_rate'])]
class User extends Authenticatable implements FilamentUser, HasRecordComments
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use RecordsComments;

    /** 財務分析の範囲: 全体 / 個人のみ (users.financial_scope) */
    public const FINANCE_OVERALL = 'overall';

    public const FINANCE_PERSONAL = 'personal';

    /** @var array<string, mixed> */
    protected $attributes = [
        'user_type' => 'employee',
        'employment_status' => 'active',
        'must_change_password' => false,
        'managed_by_env' => false,
        'financial_scope' => self::FINANCE_OVERALL,
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

    /**
     * 財務分析を表示: the money of the dashboard (売上・入出金) and 分析 → 売上分析 — ticked by an administrator on the user's
     * settings; super admins always.
     */
    public function seesFinancialAnalysis(): bool
    {
        return $this->isSuperAdmin() || (bool) $this->show_financial_analysis;
    }

    /**
     * 財務分析の範囲 (bug.md 2026-11-06): 全体 shows everyone's money (and the user may switch the dashboard to their own),
     * 個人のみ only the sales the user is the 担当者 of. Super admins always see all of it.
     */
    public function seesOverallFinance(): bool
    {
        return $this->seesFinancialAnalysis() && ($this->isSuperAdmin() || $this->financial_scope !== self::FINANCE_PERSONAL);
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

    /** @return HasMany<NotificationSetting, $this> 通知設定 (プロフィール) */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(NotificationSetting::class);
    }

    /** Where the e-mail copies of 通知 go: the 連絡用メールアドレス, else the login address. */
    public function notificationEmail(): ?string
    {
        $email = filled($this->getAttribute('contact_email')) ? $this->getAttribute('contact_email') : $this->email;

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? (string) $email : null;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->notificationEmail();
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
            'show_financial_analysis' => 'boolean',
            'app_order' => 'array',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
