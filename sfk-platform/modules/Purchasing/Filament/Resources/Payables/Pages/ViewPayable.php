<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payables\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Purchasing\Application\Payables\PayableLedger;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\Payables\PayableResource;
use Modules\Purchasing\Models\PayableMonth;

/**
 * A month of 23. 買掛管理 with the legacy record button 再集計 for administrators (詳細画面仕入再集計, Customine 23買掛 page 6):
 * the supplier's months recalculated now, e.g. after its 消費税区分 or 端数区分 changed.
 *
 * @property PayableMonth $record
 */
final class ViewPayable extends AppViewRecord
{
    protected static string $resource = PayableResource::class;

    public function getTitle(): string
    {
        return PayableResource::titleOf($this->record);
    }

    /** The legacy record button (Customine 23買掛 page 6). */
    protected function menuActions(): array
    {
        return [$this->recalculateAction()];
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PurchasingFields::PAYABLE;
    }

    private function recalculateAction(): Action
    {
        return Action::make('recalculate')
            ->label('再集計')
            ->icon(Heroicon::OutlinedCalculator)
            ->visible(fn (): bool => PayableResource::canEdit($this->record) && auth()->user() instanceof User && auth()->user()->user_type->isAdministrator())
            ->action(function (): void {
                app(PayableLedger::class)->refresh($this->record->supplier_id);
                Notification::make()->success()->title('再集計しました')->send();

                $fresh = PayableMonth::query()->find($this->record->getKey());
                $this->redirect($fresh !== null ? PayableResource::getUrl('view', ['record' => $fresh]) : PayableResource::getUrl('index'));
            });
    }
}
