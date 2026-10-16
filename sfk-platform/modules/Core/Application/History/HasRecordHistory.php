<?php

declare(strict_types=1);

namespace Modules\Core\Application\History;

use Illuminate\Database\Eloquent\Model;

/**
 * A record kept in the 変更履歴 (legacy record history). Use Modules\Core\Models\Concerns\RecordsHistory for the
 * defaults and add the record's related data (e.g. selected users) by overriding the three methods.
 */
interface HasRecordHistory
{
    /**
     * The record's content as stored in a version: its fields (no key, audit or timestamp columns) and related data.
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array;

    /**
     * An unsaved copy of the record showing the given version (更新内容を表示).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function historyPreview(array $snapshot): Model;

    /**
     * Puts the record back to the given version and saves it (このバージョンに戻す).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function restoreHistorySnapshot(array $snapshot): void;
}
