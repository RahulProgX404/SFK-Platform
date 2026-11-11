<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Models\Concerns\GrantsAppAbilities;

/**
 * One row of an app's permission list: 全員, one 従業員カテゴリ or one user, with the legacy abilities
 * (GrantsAppAbilities: without 閲覧 nothing else, one 全員 row per app).
 *
 * @property int $id
 * @property int $business_app_id
 * @property PermissionSubject $subject_type
 * @property int|null $employee_category_id
 * @property int|null $user_id
 * @property bool $can_view
 * @property bool $can_create
 * @property bool $can_update
 * @property bool $can_delete
 * @property bool $can_import
 * @property bool $can_export
 * @property bool $can_manage アプリ管理 (includes everything else)
 */
final class AppPermission extends Model
{
    use GrantsAppAbilities;

    protected $fillable = [
        'subject_type', 'employee_category_id', 'user_id',
        'can_view', 'can_create', 'can_update', 'can_delete', 'can_import', 'can_export', 'can_manage',
    ];

    protected function casts(): array
    {
        return [
            'subject_type' => PermissionSubject::class,
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_manage' => 'boolean',
        ];
    }

    protected static function ownerColumn(): string
    {
        return 'business_app_id';
    }

    /** @return BelongsTo<BusinessApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(BusinessApp::class, 'business_app_id');
    }

    /** @return BelongsTo<EmployeeCategory, $this> */
    public function employeeCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
