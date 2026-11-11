<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\ConsumableOrders;

use App\Models\User;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Models\ConsumableOrder;

/**
 * プロセス管理 of 20. 消耗品発注 (BR-PU-09): 承認依頼 (未処理 → 確認中) by its 作成者 (the legacy assignee) or an
 * administrator, 承認する (確認中 → 承認済) by an administrator — the legacy template set no approver, approving is
 * administration here. Each step notifies whom the order goes to next (legacy notification on status change): the
 * administrators on 承認依頼, the 作成者 and 発注者 on 承認する.
 */
final class ConsumableOrderWorkflow
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'purchasing.consumable-order.status';

    public function __construct(private readonly Notifier $notifier) {}

    public function canAdvance(ConsumableOrder $order, User $user): bool
    {
        // the app's administrators: administrators and アプリ管理 of 20. 消耗品発注
        $administers = AppAccess::manages($user, ConsumableOrder::APP_CODE);

        return match ($order->status) {
            ConsumableOrderStatus::Unprocessed => $administers || $order->created_by === $user->id,
            ConsumableOrderStatus::Checking => $administers,
            ConsumableOrderStatus::Approved => false,
        };
    }

    /** @param  string  $url  the order's record page */
    public function advance(ConsumableOrder $order, User $user, string $subject, string $url): ConsumableOrder
    {
        $next = $order->status->next();
        if ($next === null || ! $this->canAdvance($order, $user)) {
            throw new PurchasingActionNotAllowed('この操作を行う権限がありません。');
        }

        $action = (string) $order->status->action();
        $order->forceFill(['status' => $next])->save();

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)->about($order)->title($subject)->body($user->name.': '.$action.'（ステータス: '.$next->value.'）')->url($url),
            $next === ConsumableOrderStatus::Checking ? $this->notifier->administrators() : [$order->creator, ...$order->orderers()->get()->all()],
            $user,
        );

        return $order;
    }
}
