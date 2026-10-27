<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

/**
 * A model that deletes rows of other records itself when it is deleted (a `deleting` hook), where the foreign key
 * alone would only empty the reference — e.g. a 売上 taking its rows off the 納品書 / 請求書. RecordDeletion counts
 * those rows as deleted in the warning of the delete confirmations, and does not let such a key block the delete.
 */
interface DeletesWith
{
    /** @return array<string, string> table => column pointing at this model's records */
    public static function deletesWith(): array;
}
