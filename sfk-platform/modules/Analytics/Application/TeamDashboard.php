<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use DateTimeImmutable;
use Modules\HumanResources\Application\HrActionNotAllowed;
use Modules\HumanResources\Application\LeaveRequests\LeaveRequestWorkflow;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Modules\HumanResources\Models\LeaveRequest;
use Modules\Purchasing\Application\ConsumableOrders\ConsumableOrderWorkflow;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Filament\Resources\ConsumableOrders\ConsumableOrderResource;
use Modules\Purchasing\Models\ConsumableOrder;

/**
 * The team of the dashboard (row 3, every user):
 * - 休暇・勤怠 of today and tomorrow: the requests of 16. 申請管理 for the day (申請中 and 承認済), as far as the user may
 *   see them (employees: their own — the app's rule);
 * - クイックアクション: what waits for the user and is done with one click — the next step of 16. 申請管理 (申請する on
 *   their own 申請前 requests, 承認 of 第一申請中 for administrators) and of 20. 消耗品発注 (承認依頼 on their own 未処理
 *   orders, 承認する of 確認中 for administrators), run through the apps' workflows (notifications included).
 */
final class TeamDashboard
{
    public function __construct(
        private readonly LeaveRequestWorkflow $leaveWorkflow,
        private readonly ConsumableOrderWorkflow $orderWorkflow,
    ) {}

    /**
     * @return array{today: list<array{id: int, name: string, type: string, category: string, time: string|null, status: string, url: string}>, tomorrow: list<array{id: int, name: string, type: string, category: string, time: string|null, status: string, url: string}>}
     */
    public function absences(DateTimeImmutable $today): array
    {
        $days = ['today' => $today->format('Y-m-d'), 'tomorrow' => $today->modify('+1 day')->format('Y-m-d')];
        $requests = LeaveRequestResource::onlyVisible(LeaveRequest::query())
            ->with(['applicant:id,name', 'worker:id,name'])
            ->where('target_on', '>=', $days['today'])
            ->where('target_on', '<', $today->modify('+2 days')->format('Y-m-d'))
            ->where('status', '!=', LeaveRequestStatus::Draft->value)
            ->orderBy('target_on')->orderBy('id')
            ->get();

        $list = ['today' => [], 'tomorrow' => []];
        foreach ($requests as $request) {
            $day = $request->target_on->format('Y-m-d') === $days['today'] ? 'today' : 'tomorrow';
            $list[$day][] = [
                'id' => $request->id,
                'name' => ($request->worker ?? $request->applicant)->name,
                'type' => $request->request_type,
                'category' => $request->category,
                'time' => $request->clock_time ?? $request->total_time,
                'status' => $request->status->value,
                'url' => LeaveRequestResource::getUrl('view', ['record' => $request]),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{key: string, kind: string, id: int, title: string, subtitle: string, action: string, url: string}>
     *                                                                                                                       newest first; key = 「kind:id」 for run()
     */
    public function pendingActions(User $user, int $limit = 6): array
    {
        $actions = [];

        if (LeaveRequestResource::canViewAny()) {
            $leaves = LeaveRequestResource::onlyVisible(LeaveRequest::query())
                ->with(['applicant:id,name', 'worker:id,name'])
                ->whereIn('status', [LeaveRequestStatus::Draft->value, LeaveRequestStatus::Pending->value])
                ->latest('updated_at')->latest('id')
                ->limit(50)->get();
            foreach ($leaves as $request) {
                $step = $this->leaveStep($request, $user);
                if ($step !== null) {
                    $actions[] = [
                        'key' => 'leave:'.$request->id, 'kind' => 'leave', 'id' => $request->id, 'at' => $request->updated_at?->getTimestamp() ?? 0,
                        'title' => $step === '承認済' ? $request->request_type.'を承認' : $request->request_type.'を申請',
                        'subtitle' => (($request->worker ?? $request->applicant)->name).'・'.$request->target_on->format('n/j').' '.$request->category,
                        'action' => $step === '承認済' ? '承認' : '申請する',
                        'url' => LeaveRequestResource::getUrl('view', ['record' => $request]),
                    ];
                }
            }
        }

        if (ConsumableOrderResource::canViewAny()) {
            $orders = ConsumableOrderResource::onlyVisible(ConsumableOrder::query())
                ->with('supplier:id,name')
                ->whereIn('status', [ConsumableOrderStatus::Unprocessed->value, ConsumableOrderStatus::Checking->value])
                ->latest('updated_at')->latest('id')
                ->limit(50)->get();
            foreach ($orders as $order) {
                if ($this->orderWorkflow->canAdvance($order, $user)) {
                    $actions[] = [
                        'key' => 'order:'.$order->id, 'kind' => 'order', 'id' => $order->id, 'at' => $order->updated_at?->getTimestamp() ?? 0,
                        'title' => $order->status === ConsumableOrderStatus::Checking ? '消耗品発注を承認' : '消耗品発注の承認依頼',
                        'subtitle' => ($order->supplier->name ?? '仕入先未設定').'・'.$order->ordered_on->format('n/j'),
                        'action' => (string) $order->status->action(),
                        'url' => ConsumableOrderResource::getUrl('view', ['record' => $order]),
                    ];
                }
            }
        }

        usort($actions, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_map(fn (array $action): array => array_diff_key($action, ['at' => true]), array_slice($actions, 0, $limit));
    }

    /** The number of steps waiting for the user (pendingActions shows the newest). */
    public function pendingCount(User $user): int
    {
        return count($this->pendingActions($user, PHP_INT_MAX));
    }

    /**
     * Runs the step of a クイックアクション (「leave:12」, 「order:5」).
     *
     * @return string the message to show
     *
     * @throws HrActionNotAllowed|PurchasingActionNotAllowed when the user may not (any more)
     */
    public function run(string $key, User $user): string
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');

        if ($kind === 'leave') {
            $request = LeaveRequestResource::onlyVisible(LeaveRequest::query())->find((int) $id) ?? throw new HrActionNotAllowed('この操作を行う権限がありません。');
            $step = $this->leaveStep($request, $user) ?? throw new HrActionNotAllowed('この操作を行う権限がありません。');
            $this->leaveWorkflow->run($request, $user, $step, LeaveRequestResource::displayName().' '.LeaveRequestResource::titleOf($request), LeaveRequestResource::getUrl('view', ['record' => $request]));

            return '「'.$request->status->value.'」にしました';
        }

        if ($kind === 'order') {
            $order = ConsumableOrderResource::onlyVisible(ConsumableOrder::query())->find((int) $id) ?? throw new PurchasingActionNotAllowed('この操作を行う権限がありません。');
            $this->orderWorkflow->advance($order, $user, ConsumableOrderResource::displayName().' '.ConsumableOrderResource::titleOf($order), ConsumableOrderResource::getUrl('view', ['record' => $order]));

            return '「'.$order->status->value.'」にしました';
        }

        throw new \InvalidArgumentException('Unknown quick action.');
    }

    /** The one-click step of a request for the user: 申請する (申請前) or 承認済 (第一申請中) — never 差戻し. */
    private function leaveStep(LeaveRequest $request, User $user): ?string
    {
        $step = $request->status === LeaveRequestStatus::Draft ? '申請する' : '承認済';

        return $this->leaveWorkflow->canRun($request, $user, $step) ? $step : null;
    }
}
