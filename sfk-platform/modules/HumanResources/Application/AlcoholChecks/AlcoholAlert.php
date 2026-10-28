<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\AlcoholChecks;

use App\Models\User;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\HumanResources\Models\AlcoholCheck;

/**
 * 酒気帯び運転の有無 = 有 on an 18. アルコールチェック (BR-HR-04) as a 通知 to the administrators (the vehicle safety
 * manager must act at once): when a check is saved with 有 for 運転前 or 運転後 that was not 有 before — from the form,
 * the inline edit or anywhere else (model events created / updated: a record saved twice in a request still says
 * wasRecentlyCreated on the second save).
 */
final class AlcoholAlert
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'hr.alcohol-check.detected';

    public const DETECTED = '有';

    /** columns => the check they belong to */
    private const SIDES = ['before_detected' => '運転前', 'after_detected' => '運転後'];

    public function __construct(private readonly Notifier $notifier) {}

    public function saved(AlcoholCheck $check, ?User $actor, bool $created): void
    {
        $sides = [];
        foreach (self::SIDES as $column => $side) {
            if ($check->getAttribute($column) === self::DETECTED && ($created || $check->wasChanged($column))) {
                $sides[] = $side;
            }
        }
        if ($sides === []) {
            return;
        }

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->about($check, $check->checked_on->format('Y-m-d').' '.$check->driver->name)
                ->body('酒気帯び運転の有無: '.self::DETECTED.'（'.implode('・', $sides).'）'),
            $this->notifier->administrators(),
            $actor,
        );
    }
}
