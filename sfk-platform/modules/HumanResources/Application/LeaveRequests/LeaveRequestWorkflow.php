<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\LeaveRequests;

use App\Models\User;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * プロセス管理 of 16. 申請管理 (SYSTEM_BRAIN APP_16 workflows.md — its approver was not in the template, user 2026-11-24):
 * - 申請する (申請前 → 第一申請中) by the 申請者 / 作成者 or an administrator, who chooses the 承認者: a マネージャー
 *   (users.is_manager) who may open the app, never the 申請者. Only that manager is notified.
 * - 承認済 and 差戻し (第一申請中 → 承認済 / 申請前) by the chosen manager (while still a マネージャー) or the app's
 *   administrators (as before, so nothing is stuck while the manager is away). The 申請者 and 作成者 are notified.
 * A request sent back is sent again with 申請する, choosing a manager again.
 */
final class LeaveRequestWorkflow
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'hr.leave-request.status';

    public const SUBMIT = '申請する';

    public const APPROVE = '承認済';

    public const SEND_BACK = '差戻し';

    public function __construct(private readonly Notifier $notifier) {}

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

        // the app's administrators: administrators and アプリ管理 of 16. 申請管理
        if (AppAccess::manages($user, LeaveRequest::APP_CODE)) {
            return true;
        }

        return $request->status === LeaveRequestStatus::Draft
            ? $request->isOwnedBy($user)
            : $request->isSentTo($user) && $user->isManager();
    }

    /**
     * The マネージャー the request may be sent to: id => name. Only managers who can sign in and may open the app (else
     * they could neither see the 通知 nor the request), never the 申請者.
     *
     * @return array<int, string>
     */
    public function approverOptions(LeaveRequest $request): array
    {
        return User::query()
            ->where('is_manager', true)
            ->whereKeyNot($request->applicant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'user_type', 'employment_status'])
            ->filter(fn (User $manager): bool => $manager->employment_status->canSignIn()
                && AppAccess::can($manager, LeaveRequest::APP_CODE, AppAbility::View))
            ->mapWithKeys(fn (User $manager): array => [$manager->id => $manager->name])
            ->all();
    }

    /**
     * @param  string  $url  the request's record page
     * @param  int|null  $approverId  申請する: the chosen マネージャー (approverOptions())
     */
    public function run(LeaveRequest $request, User $user, string $action, string $subject, string $url, ?int $approverId = null): LeaveRequest
    {
        if (! $this->canRun($request, $user, $action)) {
            throw new HrActionNotAllowed('この操作を行う権限がありません。');
        }

        $next = $request->status->actions()[$action];
        $changes = ['status' => $next];
        $approver = null;
        if ($action === self::SUBMIT) {
            if ($approverId === null || ! array_key_exists($approverId, $this->approverOptions($request))) {
                throw new HrActionNotAllowed('承認者（マネージャー）を選択してください。');
            }
            $approver = User::query()->findOrFail($approverId);
            $changes['approver_id'] = $approver->id;
        }
        $request->forceFill($changes)->save();
        $request->setRelation('approver', $approver ?? $request->approver);

        $body = $action === self::SUBMIT
            ? $user->name.': '.$action.'（承認者: '.$approver?->name.'）'
            : $user->name.': '.$action.'（ステータス: '.$next->value.'）';

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)->about($request)->title($subject)->body($body)->url($url),
            $action === self::SUBMIT ? [$approver] : [$request->applicant, $request->creator],
            $user,
        );

        return $request;
    }
}
