<?php

declare(strict_types=1);

namespace Modules\Production\Application\AccidentReports;

use App\Models\User;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Production\Models\AccidentReport;

/**
 * A new 14. 事故報告管理 record as a 通知 to the administrators — the legacy review 工場長確認中 was switched off and has no
 * role here (SYSTEM_BRAIN APP_14) — and to its 営業関係者, who answer to the customer.
 */
final class AccidentAlert
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'production.accident-report.created';

    public function __construct(private readonly Notifier $notifier) {}

    /** @param  string  $name  how the record is called (its title) */
    public function created(AccidentReport $report, ?User $actor, string $name): void
    {
        $product = $report->order?->product_name;

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->about($report, $name)
                ->body('事故発生日: '.$report->occurred_on->format('Y-m-d').' / 報告者: '.$report->reporter->name.($product !== null && $product !== '' ? ' / '.$product : '')),
            [...$this->notifier->administrators()->all(), ...$report->salesUsers()->get()->all()],
            $actor,
        );
    }
}
