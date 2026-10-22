<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Models\EmployeeCategory;

/**
 * An entry of the レコード / フィールド アクセス権 (subject_type, user_id, employee_category_id): only the id that matches
 * the subject is kept, and appliesTo() tells whether the entry is for a user (作成者 depends on the record: RecordAccess).
 *
 * @property AccessSubject $subject_type
 * @property int|null $user_id
 * @property int|null $employee_category_id
 */
trait HasAccessSubject
{
    protected static function bootHasAccessSubject(): void
    {
        static::saving(function (self $entry): void {
            if ($entry->subject_type !== AccessSubject::Category) {
                $entry->employee_category_id = null;
            }
            if ($entry->subject_type !== AccessSubject::User) {
                $entry->user_id = null;
            }
        });
    }

    /** @param  list<int>  $categoryIds  the user's 従業員カテゴリ */
    public function appliesTo(User $user, array $categoryIds): bool
    {
        return match ($this->subject_type) {
            AccessSubject::User => $this->user_id === $user->id,
            AccessSubject::Category => in_array($this->employee_category_id, $categoryIds, true),
            AccessSubject::Everyone => true,
            AccessSubject::Creator => false,
        };
    }

    /** Who the entry is for, as shown in the settings (e.g. 「従業員カテゴリ：営業」). */
    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            AccessSubject::User => AccessSubject::User->label().'：'.(User::query()->whereKey($this->user_id)->value('name') ?? '—'),
            AccessSubject::Category => AccessSubject::Category->label().'：'.(EmployeeCategory::query()->whereKey($this->employee_category_id)->value('name') ?? '—'),
            default => $this->subject_type->label(),
        };
    }
}
