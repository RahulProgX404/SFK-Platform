<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Application\Records\RecordDeletion;

/**
 * Description of the first confirmation of every delete (一括削除, 選択したレコードを削除, 削除する of a list row and of a
 * record): what goes with the records, and a warning of the other apps whose records are deleted, changed or keep
 * them from being deleted (RecordDeletion::impact()).
 */
final class DeleteImpact
{
    public const PARTS = 'レコードの明細・コメント・変更履歴も削除されます。';

    /**
     * @param  class-string<Model>|Model  $model
     * @param  list<int|string>  $keys
     */
    public static function description(string|Model $model, array $keys, ?string $description = self::PARTS): HtmlString
    {
        return new HtmlString(view('core::filament.delete-impact', [
            'description' => $description,
            'impact' => app(RecordDeletion::class)->impact($model, $keys),
        ])->render());
    }
}
