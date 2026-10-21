<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables;

use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\Pages\CycleReceivableListViewSettings;
use Modules\Sales\Filament\Resources\Receivables\Pages\CycleReceivableSettings;
use Modules\Sales\Filament\Resources\Receivables\Pages\EditCycleReceivable;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListCycleReceivables;
use Modules\Sales\Filament\Resources\Receivables\Pages\ViewCycleReceivable;

/**
 * App 23-2. 売掛管理(締日別) (SYSTEM_BRAIN APP_23-2) in the 白石封筒 dashboard: 売掛 per customer × 月度 of the closing day
 * (BR-AR-01) — the balances 請求書 take (BR-SA-12). Access follows the app's permissions and status (AppResource).
 */
final class CycleReceivableResource extends ReceivableMonthResource
{
    protected static ?string $slug = 'cycle-receivables';

    protected static ?string $navigationLabel = '売掛管理(締日別)';

    protected static ?int $navigationSort = 24;

    public static function basis(): ReceivableBasis
    {
        return ReceivableBasis::Cycle;
    }

    public static function appCode(): string
    {
        return 'receivable-cycle';
    }

    public static function appName(): string
    {
        return '23-2. 売掛管理(締日別)';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCycleReceivables::route('/'),
            'views' => CycleReceivableListViewSettings::route('/views'),
            'settings' => CycleReceivableSettings::route('/settings'),
            'view' => ViewCycleReceivable::route('/{record}'),
            'edit' => EditCycleReceivable::route('/{record}/edit'),
        ];
    }
}
