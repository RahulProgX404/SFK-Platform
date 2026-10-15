<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\PermissionSubject;

/**
 * One row of an app's permission list: 全員, one 従業員カテゴリ or one user, with the legacy abilities.
 * Like the legacy system, nothing but 閲覧 makes sense without 閲覧: removing 閲覧 removes everything else.
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
 */
final class AppPermission extends Model
{
    protected $fillable = [
        'subject_type', 'employee_category_id', 'user_id',
        'can_view', 'can_create', 'can_update', 'can_delete', 'can_import', 'can_export',
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
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $permission): void {
            // Keep only the id that matches the subject.
            if ($permission->subject_type !== PermissionSubject::Category) {
                $permission->employee_category_id = null;
            }
            if ($permission->subject_type !== PermissionSubject::User) {
                $permission->user_id = null;
            }

            if (! $permission->can_view) {
                foreach (AppAbility::columns() as $column) {
                    $permission->setAttribute($column, false);
                }
            }

            $duplicateEveryone = $permission->subject_type === PermissionSubject::Everyone
                && self::query()
                    ->where('business_app_id', $permission->business_app_id)
                    ->where('subject_type', PermissionSubject::Everyone->value)
                    ->when($permission->exists, fn ($query) => $query->whereKeyNot($permission->getKey()))
                    ->exists();

            if ($duplicateEveryone) {
                throw ValidationException::withMessages(['subject_type' => '「全員」の権限は1つだけ設定できます。']);
            }
        });
    }

    public function allows(AppAbility $ability): bool
    {
        return (bool) $this->getAttribute($ability->column());
    }

    /** @return list<AppAbility> */
    public function abilities(): array
    {
        return array_values(array_filter(AppAbility::cases(), fn (AppAbility $ability): bool => $this->allows($ability)));
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
