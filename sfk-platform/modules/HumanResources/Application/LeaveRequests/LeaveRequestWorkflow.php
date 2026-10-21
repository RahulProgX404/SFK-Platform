<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\LeaveRequests;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Access\UserType;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * プロセス管理 of 16. 申請管理 (SYSTEM_BRAIN APP_16 workflows.md): 申請する (申請前 → 第一申請中) by the 申請者 / 作成者 or an
 * administrator; 承認済 and 差戻し (第一申請中 → 承認済 / 申請前) by an administrator — the legacy template set no
 * approver, approving is administration here. Each step notifies whom the request goes to next (legacy notification on
 * status change → 作業者): the administrators on 申請する, the 申請者 and 作成者 on 承認済 / 差戻し.
 */
final class LeaveRequestWorkflow
{
    /** @return list<string> the actions the user may run on the request now */
    public function actionsFor(LeaveRequest $request, User $user): array
    {
        return array_values(array_filter(array_keys($request->status->actions()), fn (string $action): bool => $this->canRun($request, $user, $action)));
    }

    public function canRun(LeaveRequest $request, User $user, string $action): bool
    {
        if (! array_key_exists($action, $request->status->actions())) {
            return false;
        }

        return $request->status === LeaveRequestStatus::Draft
            ? $user->user_type->isAdministrator() || $request->isOwnedBy($user)
            : $user->user_type->isAdministrator();
    }

    /** @param  string  $url  the request's record page */
    public function run(LeaveRequest $request, User $user, string $action, string $subject, string $url): LeaveRequest
    {
        if (! $this->canRun($request, $user, $action)) {
            throw new HrActionNotAllowed('この操作を行う権限がありません。');
        }

        $next = $request->status->actions()[$action];
        $request->forceFill(['status' => $next])->save();

        $recipients = ($next === LeaveRequestStatus::Pending
            ? User::query()->whereIn('user_type', [UserType::Admin->value, UserType::SuperAdmin->value])->get()
            : collect([$request->applicant, $request->creator])->filter())
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->is($user))
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::make()
                ->title($subject)
                ->body($user->name.': '.$action.'（ステータス: '.$next->value.'）')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->iconColor('primary')
                ->actions([Action::make('open')->label('レコードを開く')->url($url)->markAsRead()])
                ->sendToDatabase($recipients);
        }

        return $request;
    }
}
