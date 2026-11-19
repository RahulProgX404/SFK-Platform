<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Illuminate\Database\Eloquent\Model;

/**
 * Optimistic locking of the app screens (docs/legacy-customizations.md R5; the legacy REST updates carried a revision):
 * the edit page and インライン編集 remember the record's version (更新日時) when it is opened, and a save refuses to
 * overwrite a record someone else saved in between — the user reloads and edits again. Records without 更新日時 are not
 * checked.
 */
final class RecordVersion
{
    public const CONFLICT = '他のユーザーがこのレコードを更新しました。最新の内容を読み込み直してから、もう一度編集してください。';

    /** The version of the record as stored (null: the model keeps no 更新日時). */
    public static function of(Model $record): ?string
    {
        $column = $record->getUpdatedAtColumn();
        if (! $record->usesTimestamps() || $column === null || ! $record->exists) {
            return null;
        }

        $stored = $record->newQuery()->whereKey($record->getKey())->value($column);

        return $stored === null ? null : (string) $stored;
    }

    /** Whether the record is still the version that was opened. */
    public static function unchanged(Model $record, ?string $opened): bool
    {
        return $opened === null || self::of($record) === $opened;
    }
}
