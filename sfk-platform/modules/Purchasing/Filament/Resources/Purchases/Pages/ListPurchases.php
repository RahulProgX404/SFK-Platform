<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Purchasing\Application\Imports\PurchaseCsvImport;
use Modules\Purchasing\Application\Purchases\PurchaseLinks;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Filament\Resources\Purchases\Tables\PurchasesTable;
use Modules\Purchasing\Filament\Support\CsvImportAction;

/**
 * List of 05. 納品(仕入)入力. 「…」 has ファイルから読み込む (PurchaseCsvImport). Administrators have the legacy list buttons
 * 納品(仕入)入力No発番 / 受注レコード更新 / 買掛レコード更新 (Customine 05 pages 6 / 8 / 11, PurchaseLinks) and their
 * 使い方テキスト (page 10).
 */
final class ListPurchases extends AppListRecords
{
    protected static string $resource = PurchaseResource::class;

    public function listColumns(): array
    {
        return PurchasesTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_05 views.md). */
    public function defaultListViews(): array
    {
        return PurchasesTable::defaultViews();
    }

    public function filterFields(): array
    {
        return PurchasesTable::filterFields();
    }

    public function inlineFields(): array
    {
        return PurchasesTable::inlineFields();
    }

    protected function optionActions(): array
    {
        return [CsvImportAction::make(PurchaseResource::class, fn (string $contents): int => app(PurchaseCsvImport::class)->import($contents))];
    }

    protected function menuActions(): array
    {
        return [
            Action::make('numberPurchases')
                ->label('納品(仕入)入力No発番')
                ->icon(Heroicon::OutlinedHashtag)
                ->visible(fn (): bool => self::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('採番されていないレコードに納品(仕入)入力Noを発番しますか？')
                ->modalSubmitActionLabel('発番する')
                ->action(function (): void {
                    $count = app(PurchaseLinks::class)->numberUnnumbered();
                    $count === 0
                        ? Notification::make()->warning()->title('発番可能なレコードがありません。')->send()
                        : Notification::make()->success()->title('納品(仕入)入力Noの発番が完了しました。（'.$count.'件）')->send();
                }),
            Action::make('refreshOrders')
                ->label('受注レコード更新')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->visible(fn (): bool => self::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('受注レコードに仕入金額を連携しますか？')
                ->modalSubmitActionLabel('連携する')
                ->action(function (): void {
                    $count = app(PurchaseLinks::class)->refreshOrders();
                    $count === 0
                        ? Notification::make()->warning()->title('連携可能なレコードがありません。')->send()
                        : Notification::make()->success()->title('受注レコードに仕入金額を連携しました。（'.$count.'件）')->send();
                }),
            Action::make('refreshPayables')
                ->label('買掛レコード更新')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => self::isAdministrator())
                ->requiresConfirmation()
                ->modalHeading('買掛レコードに仕入金額を連携しますか？')
                ->modalSubmitActionLabel('連携する')
                ->action(function (): void {
                    $count = app(PurchaseLinks::class)->refreshPayables();
                    $count === 0
                        ? Notification::make()->warning()->title('連携可能なレコードがありません。')->send()
                        : Notification::make()->success()->title('買掛レコードに仕入金額を連携しました。（'.$count.'仕入先）')->send();
                }),
        ];
    }

    /**
     * 使い方テキスト of the buttons, for administrators (Customine 05 page 10).
     *
     * @return array<string, string>
     */
    public function menuHelp(): array
    {
        return self::isAdministrator() ? [
            '納品(仕入)入力No発番' => 'csv一括読み込み後、採番されていないレコードに対して一括で採番します。',
            '受注レコード更新' => 'csv一括読み込み後、各仕入金額を受注レコードに連携更新します。',
            '買掛レコード更新' => 'csv一括読み込み後、各仕入金額を買掛レコードに連携更新します。',
        ] : [];
    }

    private static function isAdministrator(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator();
    }
}
