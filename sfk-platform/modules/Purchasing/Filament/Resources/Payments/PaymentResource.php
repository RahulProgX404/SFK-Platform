<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Payments;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Purchasing\Filament\Resources\Payments\Pages\CreatePayment;
use Modules\Purchasing\Filament\Resources\Payments\Pages\EditPayment;
use Modules\Purchasing\Filament\Resources\Payments\Pages\ListPayments;
use Modules\Purchasing\Filament\Resources\Payments\Pages\PaymentListViewSettings;
use Modules\Purchasing\Filament\Resources\Payments\Pages\PaymentSettings;
use Modules\Purchasing\Filament\Resources\Payments\Pages\ViewPayment;
use Modules\Purchasing\Filament\Resources\Payments\Tables\PaymentsTable;
use Modules\Purchasing\Models\Payment;
use UnitEnum;

/**
 * App 07. 支払入力 (SYSTEM_BRAIN APP_07) in the 白石封筒 dashboard: payment runs with one row per supplier; the rows count
 * in the suppliers' 23. 買掛管理 of the month of 支払日 (BR-PU-08). Access follows the app's permissions and status
 * (AppResource). Like the legacy app, 一括削除 starts switched off.
 */
final class PaymentResource extends AppResource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing->value;

    protected static ?string $navigationLabel = '支払入力';

    protected static ?string $modelLabel = '支払';

    protected static ?string $pluralModelLabel = '支払';

    protected static ?int $navigationSort = 7;

    public static function appCode(): string
    {
        return 'payment-entry';
    }

    public static function appName(): string
    {
        return '07. 支払入力';
    }

    /** SYSTEM_BRAIN APP_07 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "支払入力アプリ\n支払処理に使用するアプリです。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    /** 「2026-11-25 の支払」 */
    public static function titleOf(Payment $payment): string
    {
        return $payment->paid_on->format('Y-m-d').' の支払';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'views' => PaymentListViewSettings::route('/views'),
            'settings' => PaymentSettings::route('/settings'),
            'view' => ViewPayment::route('/{record}'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
