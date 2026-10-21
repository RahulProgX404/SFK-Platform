<?php

declare(strict_types=1);

namespace Modules\HumanResources\Domain\LeaveRequests;

/**
 * プロセス管理 of 16. 申請管理 (SYSTEM_BRAIN APP_16 workflows.md): 申請前 —申請する→ 第一申請中 —承認済→ 承認済, and
 * 第一申請中 —差戻し→ 申請前. The legacy 第二申請中 was switched off and is not carried over.
 */
enum LeaveRequestStatus: string
{
    case Draft = '申請前';
    case Pending = '第一申請中';
    case Approved = '承認済';

    /**
     * The actions leading on from this status: action => status it leads to.
     *
     * @return array<string, self>
     */
    public function actions(): array
    {
        return match ($this) {
            self::Draft => ['申請する' => self::Pending],
            self::Pending => ['承認済' => self::Approved, '差戻し' => self::Draft],
            self::Approved => [],
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
