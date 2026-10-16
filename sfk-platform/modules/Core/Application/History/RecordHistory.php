<?php

declare(strict_types=1);

namespace Modules\Core\Application\History;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\RecordVersion;

/**
 * 変更履歴 of app records (legacy record history): a version for the creation and for every save that changed the
 * record (a save without changes adds none), and このバージョンに戻す, which restores a version's content and is
 * itself recorded as a new version. Versions are numbered per record from 1.
 */
final class RecordHistory
{
    /** Stores the record's current content as its next version; null when nothing changed. */
    public function record(Model&HasRecordHistory $record, ?int $userId, string $event = RecordVersion::EVENT_UPDATED, ?int $restoredFrom = null): ?RecordVersion
    {
        $fresh = $record->fresh();
        if (! $fresh instanceof HasRecordHistory) {
            return null;
        }

        return DB::transaction(function () use ($record, $fresh, $userId, $event, $restoredFrom): ?RecordVersion {
            $previous = $this->query($record)->lockForUpdate()->orderByDesc('version')->first();
            $snapshot = $fresh->historySnapshot();
            $changed = $previous === null ? [] : self::changedFields($previous->snapshot, $snapshot);

            if ($previous !== null && $changed === [] && $event !== RecordVersion::EVENT_RESTORED) {
                return null;
            }

            return RecordVersion::query()->create([
                'record_type' => $record->getMorphClass(),
                'record_id' => $record->getKey(),
                'version' => ($previous->version ?? 0) + 1,
                'event' => $previous === null ? RecordVersion::EVENT_CREATED : $event,
                'snapshot' => $snapshot,
                'changed' => $changed,
                'restored_from' => $restoredFrom,
                'user_id' => $userId,
            ]);
        });
    }

    /** @return Collection<int, RecordVersion> newest first */
    public function versions(Model $record): Collection
    {
        return $this->query($record)->with('user')->orderByDesc('version')->get();
    }

    public function find(Model $record, int $version): ?RecordVersion
    {
        return $this->query($record)->where('version', $version)->first();
    }

    /** このバージョンに戻す: the record gets the version's content, recorded as a new version. */
    public function restore(Model&HasRecordHistory $record, RecordVersion $version, ?int $userId): ?RecordVersion
    {
        return DB::transaction(function () use ($record, $version, $userId): ?RecordVersion {
            $record->restoreHistorySnapshot($version->snapshot);

            return $this->record($record, $userId, RecordVersion::EVENT_RESTORED, $version->version);
        });
    }

    /**
     * Fields whose value differs between two snapshots (a field missing on one side counts as blank).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function changedFields(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (self::normalize($before[$field] ?? null) !== self::normalize($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** @return Builder<RecordVersion> */
    private function query(Model $record): Builder
    {
        return RecordVersion::query()->where('record_type', $record->getMorphClass())->where('record_id', $record->getKey());
    }

    private static function normalize(mixed $value): string
    {
        if ($value === '' || $value === [] || $value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : (string) json_encode($value);
    }
}
