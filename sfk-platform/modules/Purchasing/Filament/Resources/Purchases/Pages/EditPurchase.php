<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use Modules\Core\Filament\AppPages\AppEditRecord;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\Purchase;

/**
 * Editing a purchase of 05. 納品(仕入)入力. When 仕入原価超過 or 通知先 changed (and an overrun is checked) the 通知先 are
 * notified (BR-PU-05).
 *
 * @property Purchase $record
 */
final class EditPurchase extends AppEditRecord
{
    use SendsOverrunAlert;

    protected static string $resource = PurchaseResource::class;

    /** @var array{0: list<string>, 1: list<int>} 仕入原価超過 and 通知先 before the save */
    private array $alertBefore = [[], []];

    public function getTitle(): string
    {
        return (string) $this->record->purchase_number;
    }

    protected function beforeSave(): void
    {
        $this->alertBefore = $this->alertState($this->record);
    }

    protected function afterSave(): void
    {
        parent::afterSave();

        if ($this->alertState($this->record->fresh() ?? $this->record) !== $this->alertBefore) {
            $this->sendOverrunAlert($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /** @return array{0: list<string>, 1: list<int>} */
    private function alertState(Purchase $purchase): array
    {
        $overruns = (array) $purchase->overruns;
        sort($overruns);

        return [$overruns, $purchase->notifiedUsers()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all()];
    }
}
