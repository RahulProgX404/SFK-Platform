<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Illuminate\Database\Eloquent\Model;

/**
 * What a record of an app must be, wherever it is saved — its quick entry (Core QuickEntry), its edit page (AppEditRecord)
 * and インライン編集 (AppListRecords): required fields, values that must differ, values that must be unique and the rules
 * between fields. One class per app (AppResource::recordRules()), so the three screens cannot disagree.
 *
 * The values are those of the record by attribute: a table as a list of rows (any keys), several users as a list of ids;
 * numbers, ids and times may come as text (H:i or H:i:s), a check as true / 1 / ['✓'].
 */
interface RecordRules
{
    /**
     * The saved record's values, in that shape (what the screen did not send is taken from here).
     *
     * @return array<string, mixed>
     */
    public function values(Model $record): array;

    /**
     * Laravel rules by path (a table's rows by their keys in $data, e.g. `damages.3.cost`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function rules(array $data, ?Model $record): array;

    /** @return array<string, string> messages by `<path>.<rule>` (wildcards allowed) */
    public function messages(): array;

    /** @return array<string, string> labels by path (wildcards allowed) */
    public function attributes(): array;
}
