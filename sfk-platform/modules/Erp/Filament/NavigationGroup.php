<?php

declare(strict_types=1);

namespace Modules\Erp\Filament;

/**
 * Sidebar groups of the ERP panel, in display order. Business modules assign their resources
 * with `protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;`
 * (mirrors the app groups in SYSTEM_BRAIN 01_SYSTEM_OVERVIEW).
 */
enum NavigationGroup: string
{
    case MasterData = 'マスタ';
    case Orders = '受注・見積';
    case Production = '生産';
    case Purchasing = '購買・支払';
    case Sales = '売上・請求';
    case Accounting = '売掛・買掛';
    case Analytics = '分析';
    case HumanResources = '人事・安全';
    case System = 'システム';

    /** @return list<string> */
    public static function labels(): array
    {
        return array_map(fn (self $group): string => $group->value, self::cases());
    }
}
