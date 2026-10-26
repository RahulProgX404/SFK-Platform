<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Records\RecordDeletion;

/**
 * 削除する of a record that other records still use is refused with what uses it (RecordDeletion), instead of a
 * database error. Its own parts are deleted with it.
 */
final class GuardedDelete
{
    public static function apply(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Model $record): void {
            $reason = app(RecordDeletion::class)->blocker($record);
            if ($reason !== null) {
                Notification::make()->danger()->title('削除できません')->body($reason)->persistent()->send();
                $action->cancel();
            }
        });
    }
}
