<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\RecordVersion;

/**
 * Default 変更履歴 behaviour of a record (Modules\Core\Application\History\HasRecordHistory): the snapshot is every
 * column except the key, the audit columns (作成者 / 更新者) and the timestamps; enums and dates are stored as plain
 * values. Restoring writes those columns back.
 *
 * @mixin Model
 */
trait RecordsHistory
{
    /** A deleted record takes its 変更履歴 with it. */
    public static function bootRecordsHistory(): void
    {
        static::deleted(function (Model $record): void {
            RecordVersion::query()->where('record_type', $record->getMorphClass())->where('record_id', $record->getKey())->delete();
        });
    }

    /** @return list<string> columns never kept in or restored from a version */
    protected function historyExcludedColumns(): array
    {
        return [$this->getKeyName(), 'created_by', 'updated_by', $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()];
    }

    /** @return array<string, mixed> */
    public function historySnapshot(): array
    {
        $snapshot = [];
        foreach (array_keys($this->getAttributes()) as $column) {
            if (in_array($column, $this->historyExcludedColumns(), true)) {
                continue;
            }

            $value = $this->getAttribute($column);
            $snapshot[$column] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = clone $this;
        $copy->forceFill($this->historyColumns($snapshot));

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        $this->forceFill($this->historyColumns($snapshot))->save();
    }

    /**
     * The snapshot's values of columns the record has (related data and removed columns are left out).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function historyColumns(array $snapshot): array
    {
        $columns = array_diff(array_keys($this->getAttributes()), $this->historyExcludedColumns());

        return array_intersect_key($snapshot, array_flip($columns));
    }
}
