<?php

declare(strict_types=1);

namespace Modules\Core\Application\Apps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Apps\AccessSubject;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Models\AppFieldPermission;
use Modules\Core\Models\BusinessApp;

/**
 * フィールドのアクセス権 of an app as the settings screen edits them: per field (once each) its entries in order — who
 * (個人 / 従業員カテゴリ / 全員) may 編集 / 閲覧 it, or nothing. Saving replaces all entries of the app. Administrators only.
 */
final class FieldPermissionService
{
    /** @return list<array{field: string, entries: list<array<string, mixed>>}> the form state, fields in the order they were set */
    public function state(BusinessApp $app): array
    {
        return $app->fieldPermissions()->get()
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->groupBy('field')
            ->sortBy(fn ($entries) => $entries->min('id'))
            ->map(fn ($entries, $field): array => [
                'field' => (string) $field,
                'entries' => $entries->map(fn (AppFieldPermission $entry): array => [
                    'subject_type' => $entry->subject_type->value,
                    'user_id' => $entry->user_id,
                    'employee_category_id' => $entry->employee_category_id,
                    'access' => $entry->access->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Replaces the app's field entries with the form state.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, string>  $known  the app's fields (name => label)
     */
    public function save(BusinessApp $app, array $fields, array $known, User $actor, string $statePath = 'fields'): void
    {
        if (! $actor->user_type->isAdministrator()) {
            throw new AppActionNotAllowed('アクセス権を設定できるのは管理者のみです。');
        }

        $seen = [];
        foreach ($fields as $index => $field) {
            $name = (string) ($field['field'] ?? '');
            if (! array_key_exists($name, $known)) {
                throw ValidationException::withMessages([$statePath => ($index + 1).'番目の設定：フィールドを選択してください。']);
            }
            if (in_array($name, $seen, true)) {
                throw ValidationException::withMessages([$statePath => ($index + 1).'番目の設定：同じフィールドは1回だけ設定できます。']);
            }
            if (array_values((array) ($field['entries'] ?? [])) === []) {
                throw ValidationException::withMessages([$statePath => ($index + 1).'番目の設定：対象を1つ以上設定してください。']);
            }
            $seen[] = $name;
        }

        DB::transaction(function () use ($app, $fields): void {
            $app->fieldPermissions()->delete();

            foreach ($fields as $field) {
                foreach (array_values((array) ($field['entries'] ?? [])) as $order => $entry) {
                    $app->fieldPermissions()->create([
                        'field' => (string) $field['field'],
                        'sort_order' => $order + 1,
                        'subject_type' => AccessSubject::tryFrom((string) ($entry['subject_type'] ?? '')) ?? AccessSubject::Everyone,
                        'user_id' => filled($entry['user_id'] ?? null) ? (int) $entry['user_id'] : null,
                        'employee_category_id' => filled($entry['employee_category_id'] ?? null) ? (int) $entry['employee_category_id'] : null,
                        'access' => FieldAccessLevel::tryFrom((string) ($entry['access'] ?? '')) ?? FieldAccessLevel::None,
                    ]);
                }
            }
        });
    }
}
