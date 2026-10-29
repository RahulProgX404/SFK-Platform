<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;

/**
 * Writing a legacy record into its table, the same way for every app: matched by `legacy_id` (the legacy record
 * number), which also becomes the `id` when that is free — so the numbers people know (受注ID, 売上ID …) stay the
 * same — and written straight into the table (no model events: 作成者 / 更新者 and the times are the legacy ones).
 * A record new to the system starts its 変更履歴 with version 1 by its 更新者 at its 更新日時.
 */
final class LegacyRows
{
    public function __construct(private readonly RecordHistory $history) {}

    /**
     * @param  array<string, mixed>  $attributes  the columns (legacy_id is set here)
     * @param  bool  $keepId  the legacy number also becomes the id when free (false: a number of another kind, e.g.
     *                        a table row's id)
     * @return array{0: int, 1: bool} the row's id, and whether it was created
     */
    public function save(string $table, int $legacyId, array $attributes, bool $keepId = true): array
    {
        $attributes['legacy_id'] = $legacyId;
        $id = DB::table($table)->where('legacy_id', $legacyId)->value('id');
        if ($id !== null) {
            DB::table($table)->where('id', $id)->update($attributes);

            return [(int) $id, false];
        }

        $free = $keepId && ! DB::table($table)->where('id', $legacyId)->exists();

        return [(int) DB::table($table)->insertGetId([...($free ? ['id' => $legacyId] : []), ...$attributes]), true];
    }

    /**
     * The rows of a record's table (e.g. 見積内容) replaced by the legacy ones.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceRows(string $table, string $foreignKey, int $parentId, array $rows): void
    {
        DB::table($table)->where($foreignKey, $parentId)->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->insert(array_map(fn (array $row): array => [$foreignKey => $parentId, ...$row], $chunk));
        }
    }

    /** Version 1 of a record's 変更履歴 (new records), by its 更新者 at its 更新日時. */
    public function startHistory(Model&HasRecordHistory $record, ?int $userId, ?string $at): void
    {
        $version = $this->history->record($record, $userId);
        if ($version !== null && $at !== null) {
            $version->forceFill(['created_at' => $at])->saveQuietly();
        }
    }

    /** Ids were taken from the legacy record numbers: new records continue after the highest one (PostgreSQL). */
    public function syncIdSequence(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), GREATEST((SELECT COALESCE(MAX(id), 0) FROM {$table}), 1))");
        }
    }

    /**
     * The legacy record numbers already taken over into a table.
     *
     * @return array<int, int> legacy_id => id
     */
    public function ids(string $table): array
    {
        return DB::table($table)->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
