<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\AppResource;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;

/**
 * 「…」 → ファイルから読み込む of a purchasing app list (legacy CSV import): a CSV file with the field labels as headers,
 * for users with ファイル読み込み and 追加 on the app. The problems found are shown and nothing is imported.
 */
final class CsvImportAction
{
    /**
     * @param  class-string<AppResource>  $resource
     * @param  Closure(string): int  $import  imports the file contents, returns the number of records
     */
    public static function make(string $resource, Closure $import): Action
    {
        return Action::make('importCsv')
            ->label('ファイルから読み込む')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => $resource::appCan(AppAbility::Import) && $resource::canCreate())
            ->modalHeading('ファイルから読み込む')
            ->modalWidth('md')
            ->modalSubmitActionLabel('読み込む')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                FileUpload::make('file')
                    ->label('CSVファイル')
                    // by extension: the type sniffed from a CSV file (UTF-8 / Shift_JIS) is not reliable
                    ->rule('extensions:csv,txt')
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data, Action $action) use ($import): void {
                $file = $data['file'] ?? null;
                $file = is_array($file) ? reset($file) : $file;
                if (! $file instanceof TemporaryUploadedFile) {
                    return;
                }

                try {
                    $count = $import((string) $file->get());
                } catch (PurchasingActionNotAllowed $exception) {
                    Notification::make()->danger()->title('読み込めませんでした')->body($exception->getMessage())->persistent()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title($count.'件のレコードを読み込みました')->send();
            });
    }
}
