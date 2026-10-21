<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables;

use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Filament\Resources\Receivables\Pages\EditReceivable;
use Modules\Sales\Filament\Resources\Receivables\Pages\ListReceivables;
use Modules\Sales\Filament\Resources\Receivables\Pages\ReceivableListViewSettings;
use Modules\Sales\Filament\Resources\Receivables\Pages\ReceivableSettings;
use Modules\Sales\Filament\Resources\Receivables\Pages\ViewReceivable;

/**
 * App 23. 売掛管理 (SYSTEM_BRAIN APP_23) in the 白石封筒 dashboard: 売掛 per customer × calendar month (売上日 / 入金日).
 * Access follows the app's permissions and status (AppResource).
 */
final class ReceivableResource extends ReceivableMonthResource
{
    protected static ?string $slug = 'receivables';

    protected static ?string $navigationLabel = '売掛管理';

    protected static ?int $navigationSort = 23;

    public static function basis(): ReceivableBasis
    {
        return ReceivableBasis::Calendar;
    }

    public static function appCode(): string
    {
        return 'receivable';
    }

    public static function appName(): string
    {
        return '23. 売掛管理';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceivables::route('/'),
            'views' => ReceivableListViewSettings::route('/views'),
            'settings' => ReceivableSettings::route('/settings'),
            'view' => ViewReceivable::route('/{record}'),
            'edit' => EditReceivable::route('/{record}/edit'),
        ];
    }
}
