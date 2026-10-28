<?php

declare(strict_types=1);

namespace Modules\Orders\Application\Orders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\NotificationCheck;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Models\Order;

/**
 * 納期 of 04. 受注入力 coming up: every morning, the jobs due today or on the next weekday (over a weekend: Friday to
 * Monday) whose ステータス is not 発送完了 notify their 担当者 (without one, the 作成者) — once per job and 納期, so a
 * moved 納期 is announced again.
 */
final class OrderDueCheck implements NotificationCheck
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'orders.order.due';

    public function __construct(private readonly Notifier $notifier) {}

    public function run(CarbonImmutable $today): void
    {
        $until = self::nextWeekday($today);

        Order::query()
            ->with(['assignees', 'creator'])
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $until->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', '!=', OrderChoices::STATUS_SHIPPED))
            ->orderBy('id')
            ->chunk(200, function ($orders) use ($today): void {
                foreach ($orders as $order) {
                    /** @var Order $order */
                    $due = CarbonImmutable::parse($order->getAttribute('due_date'));
                    $this->notifier->send(
                        Notice::make(self::NOTIFICATION)
                            ->about($order, $order->order_number)
                            ->body(self::dueText($due, $order->due_time, $today).(filled($order->product_name) ? ' / '.$order->product_name : ''))
                            ->once(self::NOTIFICATION.':'.$order->id.':'.$due->toDateString()),
                        $order->assignees->isNotEmpty() ? $order->assignees : [$order->creator],
                    );
                }
            });
    }

    /** The first Monday – Friday after the day. */
    public static function nextWeekday(CarbonImmutable $day): CarbonImmutable
    {
        $next = $day->addDay();
        while ($next->isWeekend()) {
            $next = $next->addDay();
        }

        return $next;
    }

    /** 「納期: 2026-10-29 10:00（明日）」 */
    public static function dueText(CarbonImmutable $due, ?string $time, CarbonImmutable $today): string
    {
        $when = match (true) {
            $due->isSameDay($today) => '本日',
            $due->isSameDay($today->addDay()) => '明日',
            default => ['日', '月', '火', '水', '木', '金', '土'][$due->dayOfWeek].'曜日',
        };

        return '納期: '.$due->format('Y-m-d').(filled($time) ? ' '.substr((string) $time, 0, 5) : '').'（'.$when.'）';
    }
}
