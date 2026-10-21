<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * A request of 16. 申請管理. The toolbar has the プロセス管理 actions of its status the user may run — 申請する (申請前),
 * 承認済 and 差戻し (第一申請中, administrators) — which move it on and notify whom it goes to (LeaveRequestWorkflow).
 *
 * @property LeaveRequest $record
 */
final class ViewLeaveRequest extends AppViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    public function getTitle(): string
    {
        return LeaveRequestResource::titleOf($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->processAction('submit', '申請する', Heroicon::OutlinedPaperAirplane),
            $this->processAction('approve', '承認済', Heroicon::OutlinedCheckCircle),
            $this->processAction('sendBack', '差戻し', Heroicon::OutlinedArrowUturnLeft),
            ...parent::getHeaderActions(),
        ];
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return HrFields::LEAVE;
    }

    private function processAction(string $name, string $action, Heroicon $icon): Action
    {
        $user = fn (): ?User => auth()->user() instanceof User ? auth()->user() : null;

        return Action::make($name)
            ->label($action)
            ->icon($icon)
            ->visible(fn (): bool => $this->historyVersion === null && $user() !== null
                && app(LeaveRequestWorkflow::class)->canRun($this->record, $user(), $action))
            ->requiresConfirmation()
            ->modalHeading($action)
            ->modalSubmitActionLabel('実行')
            ->modalCancelActionLabel('キャンセル')
            ->action(function () use ($user, $action): void {
                $actor = $user();
                if ($actor === null) {
                    return;
                }

                try {
                    app(LeaveRequestWorkflow::class)->run(
                        $this->record,
                        $actor,
                        $action,
                        LeaveRequestResource::appName().' '.LeaveRequestResource::titleOf($this->record),
                        LeaveRequestResource::getUrl('view', ['record' => $this->record]),
                    );
                } catch (HrActionNotAllowed $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('ステータスを「'.$this->record->status->value.'」にしました')->send();
                $this->redirect(LeaveRequestResource::getUrl('view', ['record' => $this->record]));
            });
    }
}
