<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Sales\Application\Documents\DeliveryNoteIssuer;
use Modules\Sales\Application\SalesActionNotAllowed;
use Modules\Sales\Domain\Sales\SaleFields;
use Modules\Sales\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\Sales\Filament\Resources\Sales\SaleResource;
use Modules\Sales\Filament\Resources\Sales\Schemas\SaleForm;
use Modules\Sales\Models\Sale;

/**
 * A sale of 06. 売上入力. 「…」 has 納品書を発行 for administrators (legacy detail button 「納品書発行あり」, Customine 06
 * page 5): a delivery note of this sale alone when it has none yet, then its record (to print); with a note already
 * there, that note opens.
 *
 * @property Sale $record
 */
final class ViewSale extends AppViewRecord
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        return (string) $this->record->sales_number;
    }

    protected function getHeaderActions(): array
    {
        return array_map(function (Action|ActionGroup $action): Action|ActionGroup {
            if ($action instanceof ActionGroup) {
                $action->actions([$this->issueDeliveryNoteAction(), ...$action->getActions()]);
            }

            return $action;
        }, parent::getHeaderActions());
    }

    private function issueDeliveryNoteAction(): Action
    {
        return Action::make('issueDeliveryNote')
            ->label('納品書を発行')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (): bool => SaleForm::isAdministrator() && $this->historyVersion === null)
            ->requiresConfirmation(fn (): bool => $this->record->delivery_note_id === null)
            ->modalHeading('この売上の納品書を作成しますか？')
            ->modalSubmitActionLabel('作成する')
            ->action(function (): void {
                $note = $this->record->deliveryNote;
                if ($note === null) {
                    try {
                        $note = app(DeliveryNoteIssuer::class)->issue([$this->record])[0];
                    } catch (SalesActionNotAllowed $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title('納品書を作成しました')->send();
                }

                $this->redirect(DeliveryNoteResource::getUrl('view', ['record' => $note]));
            });
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return SaleFields::LABELS;
    }
}
