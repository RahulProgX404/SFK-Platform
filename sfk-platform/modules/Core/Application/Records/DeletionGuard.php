<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Illuminate\Database\Eloquent\Model;

/**
 * Business rules of a module about deleting its records that the foreign keys cannot express (registered with
 * RecordDeletion::guard()): a reason the record may not go (e.g. a 売上 on an invoice that already has payments), and
 * what else the delete does, for the warning of the delete confirmations (e.g. 納品書 left without rows deleted too).
 */
interface DeletionGuard
{
    /** Why the record cannot be deleted, or null when it can. */
    public function blocker(Model $record): ?string;

    /**
     * @param  list<int|string>  $keys  the records about to be deleted
     * @return list<array{app: string, text: string, blocks: bool}>
     */
    public function impact(array $keys): array;
}
