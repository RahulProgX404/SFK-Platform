<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\LeaveRequests\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Domain\HrFields;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * A request of 16. 申請管理. The toolbar has the プロセス管理 actions of its status the user may run — 申請する (申請前; its
 * dialog asks for the 承認者, a マネージャー), 承認済 and 差戻し (第一申請中: the chosen manager and the app's administrators)
 * — which move it on and notify whom it goes to (LeaveRequestWorkflow). ?action=submit opens 申請する (クイックアクション).
 *
 * @property LeaveRequest $record
 */
final class ViewLeaveRequest extends AppViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    /** @var array<int, string>|null the マネージャー of 申請する, read once per request while its dialog is open */
    private ?array $approverChoices = null;

    public function getTitle(): string
    {
        return LeaveRequestResource::titleOf($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->processAction('submit', LeaveRequestWorkflow::SUBMIT, Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation(false)
                ->modalIcon(Heroicon::OutlinedPaperAirplane)
                ->modalWidth('md')
                ->fillForm(fn (): array => ['approver_id' => $this->record->approver_id])
                ->schema([
                    Select::make('approver_id')
                        ->label('承認者')
                        ->placeholder('-----')
                        ->options(fn (): array => $this->approverChoices())
                        ->in(fn (): array => array_keys($this->approverChoices()))
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->validationMessages(['required' => '承認者（マネージャー）を選択してください。']),
                ]),
            $this->processAction('approve', LeaveRequestWorkflow::APPROVE, Heroicon::OutlinedCheckCircle),
            $this->processAction('sendBack', LeaveRequestWorkflow::SEND_BACK, Heroicon::OutlinedArrowUturnLeft),
            ...parent::getHeaderActions(),
        ];
    }

    /** 申請者, 作業員名 and 承認者 of the record page, with 作成者 / 更新者: one query for the users. */
    protected function batchedRelations(): array
    {
        return [...parent::batchedRelations(), 'applicant', 'worker', 'approver'];
    }

    /** @return array<int, string> */
    private function approverChoices(): array
    {
        return $this->approverChoices ??= app(LeaveRequestWorkflow::class)->approverOptions($this->record);
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
            ->action(function (array $data) use ($user, $action): void {
                $actor = $user();
                if ($actor === null) {
                    return;
                }

                try {
                    app(LeaveRequestWorkflow::class)->run(
                        $this->record,
                        $actor,
                        $action,
                        LeaveRequestResource::displayName().' '.LeaveRequestResource::titleOf($this->record),
                        LeaveRequestResource::getUrl('view', ['record' => $this->record]),
                        filled($data['approver_id'] ?? null) ? (int) $data['approver_id'] : null,
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
