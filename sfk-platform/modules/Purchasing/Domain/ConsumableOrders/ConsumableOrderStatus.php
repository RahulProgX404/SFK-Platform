<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain\ConsumableOrders;

/**
 * プロセス管理 of 20. 消耗品発注 (BR-PU-09, SYSTEM_BRAIN APP_20 workflows.md): 未処理 —承認依頼→ 確認中 —承認する→ 承認済.
 */
enum ConsumableOrderStatus: string
{
    case Unprocessed = '未処理';
    case Checking = '確認中';
    case Approved = '承認済';

    /** The action leading on from this status (null at the end). */
    public function action(): ?string
    {
        return match ($this) {
            self::Unprocessed => '承認依頼',
            self::Checking => '承認する',
            self::Approved => null,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Unprocessed => self::Checking,
            self::Checking => self::Approved,
            self::Approved => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->value;
        }

        return $options;
    }
}
