<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Records\RecordDeletion;

/**
 * 削除する of a record that other records still use is refused with what uses it (RecordDeletion), instead of a
 * database error. Its own parts are deleted with it; the confirmation warns of the other apps' records it affects
 * (DeleteImpact).
 */
final class GuardedDelete
{
    public static function apply(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription(fn (Model $record): HtmlString => DeleteImpact::description($record, [$record->getKey()]))
            ->before(function (DeleteAction $action, Model $record): void {
                $reason = app(RecordDeletion::class)->blocker($record);
                if ($reason !== null) {
                    Notification::make()->danger()->title('削除できません')->body($reason)->persistent()->send();
                    $action->cancel();
                }
            });
    }
}
