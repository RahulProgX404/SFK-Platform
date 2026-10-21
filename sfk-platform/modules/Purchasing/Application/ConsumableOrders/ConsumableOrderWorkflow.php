<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\ConsumableOrders;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Access\UserType;
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
    public function canAdvance(ConsumableOrder $order, User $user): bool
    {
        return match ($order->status) {
            ConsumableOrderStatus::Unprocessed => $user->user_type->isAdministrator() || $order->created_by === $user->id,
            ConsumableOrderStatus::Checking => $user->user_type->isAdministrator(),
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

        $recipients = ($next === ConsumableOrderStatus::Checking
            ? User::query()->whereIn('user_type', [UserType::Admin->value, UserType::SuperAdmin->value])->get()
            : collect([$order->creator, ...$order->orderers()->get()->all()])->filter())
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

        return $order;
    }
}
