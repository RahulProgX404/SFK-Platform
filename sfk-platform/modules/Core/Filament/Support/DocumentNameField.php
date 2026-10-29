<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Models\User;
use Filament\Forms\Components\Select;
use Modules\Core\Application\Documents\DocumentSource;
use Modules\Core\Filament\AppResource;

/**
 * 帳票出力: the field the user chooses for 「{ユーザー選択}」 of a 出力設定's ファイル名 when putting the 帳票 out (ファイル名に
 * フィールドを追加 → ユーザーが選択するフィールド) — one of the fields of the app's record page.
 */
final class DocumentNameField
{
    /** @param  class-string<AppResource>  $resource */
    public static function make(string $resource): Select
    {
        return Select::make('name_field')
            ->label('ファイル名にするフィールド')
            ->options(function () use ($resource): array {
                $user = auth()->user();

                return DocumentSource::for($resource, $user instanceof User ? $user : null)->fields();
            })
            ->native(false)
            ->searchable()
            ->required();
    }
}
