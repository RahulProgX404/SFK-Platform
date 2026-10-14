<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Core\Access\PanelPermission;
use Modules\Core\Access\Roles;
use Modules\Core\Domain\People\EmployeeCategory;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationMembership;
use Spatie\Permission\Traits\HasRoles;

/**
 * One user base for the whole platform. Which panels a user may open (ERP, SNS, SEO, settings …) is
 * decided by roles/permissions, not by separate accounts. The employee profile (氏名, 在籍状況, 所属)
 * lives on the same record (SYSTEM_BRAIN APP_02 "Employee ↔ system user link").
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property EmploymentStatus $employment_status
 * @property EmployeeCategory|null $employee_category
 * @property bool $must_change_password
 * @property bool $managed_by_env
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_changed_at
 */
#[Fillable([
    'name', 'email', 'password',
    'last_name', 'first_name', 'last_name_kana', 'first_name_kana',
    'employee_code', 'employee_category', 'employment_status',
    'birthday', 'hired_on', 'left_on',
    'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
    'emergency_contact_name', 'emergency_contact_phone', 'notes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'employment_status' => 'active',
        'must_change_password' => false,
        'managed_by_env' => false,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }

        return $this->employment_status->canSignIn()
            && $this->checkPermissionTo(PanelPermission::for($panel->getId()));
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return BelongsToMany<Organization, $this, OrganizationMembership> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationMembership::class)
            ->withPivot(['id', 'position_id', 'is_primary'])
            ->withTimestamps();
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
            'employment_status' => EmploymentStatus::class,
            'employee_category' => EmployeeCategory::class,
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
