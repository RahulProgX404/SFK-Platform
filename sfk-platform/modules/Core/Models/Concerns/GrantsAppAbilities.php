<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\PermissionSubject;

/**
 * A permission row for 全員, one 従業員カテゴリ or one user with the legacy abilities (`can_*`, AppAbility) — the rows
 * of an app (AppPermission) and of a space (SpacePermission). Like the legacy system, nothing but 閲覧 makes sense
 * without 閲覧: removing 閲覧 removes everything else; only the id matching the subject is kept; one 全員 row per owner.
 * アプリ管理 (AppAbility::Manage) ticks every other ability.
 *
 * @property PermissionSubject $subject_type
 * @property int|null $employee_category_id
 * @property int|null $user_id
 */
trait GrantsAppAbilities
{
    /** The column of the app / space the row belongs to. */
    abstract protected static function ownerColumn(): string;

    protected static function bootGrantsAppAbilities(): void
    {
        static::saving(function (self $permission): void {
            if ($permission->subject_type !== PermissionSubject::Category) {
                $permission->employee_category_id = null;
            }
            if ($permission->subject_type !== PermissionSubject::User) {
                $permission->user_id = null;
            }

            // アプリ管理 includes every other ability
            if ($permission->getAttribute(AppAbility::Manage->column())) {
                foreach (AppAbility::columns() as $column) {
                    $permission->setAttribute($column, true);
                }
            }

            if (! $permission->getAttribute('can_view')) {
                foreach (AppAbility::columns() as $column) {
                    $permission->setAttribute($column, false);
                }
            }

            $owner = static::ownerColumn();
            $duplicateEveryone = $permission->subject_type === PermissionSubject::Everyone
                && static::query()
                    ->where($owner, $permission->getAttribute($owner))
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
        return (bool) $this->getAttribute($ability->column()) || (bool) $this->getAttribute(AppAbility::Manage->column());
    }

    /** @return list<AppAbility> */
    public function abilities(): array
    {
        return array_values(array_filter(AppAbility::cases(), fn (AppAbility $ability): bool => $this->allows($ability)));
    }

    /**
     * Rows that apply to the user: 全員, their own row and the rows of their 従業員カテゴリ.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $categoryIds
     */
    public function scopeApplyingTo(Builder $query, User $user, array $categoryIds): void
    {
        $query->where(function (Builder $query) use ($user, $categoryIds): void {
            $query->where('subject_type', PermissionSubject::Everyone->value)
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', PermissionSubject::User->value)
                    ->where('user_id', $user->id))
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', PermissionSubject::Category->value)
                    ->whereIn('employee_category_id', $categoryIds));
        });
    }

    /**
     * Header of a row, e.g. 「全員」「従業員カテゴリ：営業」「個人：山田 花子」 (relations loaded when possible).
     */
    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            PermissionSubject::User => '個人：'.($this->user->name ?? '—'),
            PermissionSubject::Category => '従業員カテゴリ：'.($this->employeeCategory->name ?? '—'),
            PermissionSubject::Everyone => '全員',
        };
    }
}
