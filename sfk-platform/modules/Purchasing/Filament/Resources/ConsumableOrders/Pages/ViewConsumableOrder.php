<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\ConsumableOrders\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableOrderWorkflow;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Models\ConsumableOrder;

/**
 * An order of 20. 消耗品発注. The toolbar has the プロセス管理 action of its status — 承認依頼 (未処理, its 作成者 or an
 * administrator) or 承認する (確認中, administrators) — which moves it on and notifies whom it goes to (BR-PU-09).
 *
 * @property ConsumableOrder $record
 */
final class ViewConsumableOrder extends AppViewRecord
{
    protected static string $resource = ConsumableOrderResource::class;

    public function getTitle(): string
    {
        return ConsumableOrderResource::titleOf($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [$this->advanceAction(), ...parent::getHeaderActions()];
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PurchasingFields::CONSUMABLE_ORDER;
    }

    private function advanceAction(): Action
    {
        $user = fn (): ?User => auth()->user() instanceof User ? auth()->user() : null;

        return Action::make('advanceStatus')
            ->label(fn (): string => (string) $this->record->status->action())
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (): bool => $this->historyVersion === null && $user() !== null
                && app(ConsumableOrderWorkflow::class)->canAdvance($this->record, $user()))
            ->requiresConfirmation()
            ->modalHeading(fn (): string => (string) $this->record->status->action())
            ->modalSubmitActionLabel('実行')
            ->modalCancelActionLabel('キャンセル')
            ->action(function () use ($user): void {
                $actor = $user();
                if ($actor === null) {
                    return;
                }

                try {
                    app(ConsumableOrderWorkflow::class)->advance(
                        $this->record,
                        $actor,
                        ConsumableOrderResource::appName().' '.ConsumableOrderResource::titleOf($this->record),
                        ConsumableOrderResource::getUrl('view', ['record' => $this->record]),
                    );
                } catch (PurchasingActionNotAllowed $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('ステータスを「'.$this->record->status->value.'」にしました')->send();
                $this->redirect(ConsumableOrderResource::getUrl('view', ['record' => $this->record]));
            });
    }
}
