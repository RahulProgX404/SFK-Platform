<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\CreateDeliveryNote;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\DeliveryNoteListViewSettings;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\DeliveryNoteSettings;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\EditDeliveryNote;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ListDeliveryNotes;
use Modules\Sales\Filament\Resources\DeliveryNotes\Pages\ViewDeliveryNote;
use Modules\Sales\Filament\Resources\DeliveryNotes\Tables\DeliveryNotesTable;
use Modules\Sales\Models\DeliveryNote;
use UnitEnum;

/**
 * App 10. 納品書管理(売上) (SYSTEM_BRAIN APP_10) in the 白石封筒 dashboard: notes are made from 06. 売上入力 (納品書を作成)
 * and can be corrected or typed here. Access follows the app's permissions and status (AppResource). Like the legacy
 * app, 一括削除 starts switched off.
 */
final class DeliveryNoteResource extends AppResource
{
    protected static ?string $model = DeliveryNote::class;

    protected static ?string $slug = 'delivery-notes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Sales->value;

    protected static ?string $navigationLabel = '納品書管理(売上)';

    protected static ?string $modelLabel = '納品書';

    protected static ?string $pluralModelLabel = '納品書';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 10;

    public static function appCode(): string
    {
        return 'delivery-note';
    }

    public static function appName(): string
    {
        return '10. 納品書管理(売上)';
    }

    /** SYSTEM_BRAIN APP_10 README (app description in the legacy system). */
    public static function appDescription(): string
    {
        return "納品書管理アプリ\n納品書を作成するアプリです。";
    }

    /** The legacy app template: 一括削除 off (enableBulkDeletion false), everything else on. */
    public static function appSettingDefaults(): array
    {
        return ['bulk_delete_enabled' => false];
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryNoteLayout::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeliveryNoteLayout::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryNotesTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryNotes::route('/'),
            'create' => CreateDeliveryNote::route('/create'),
            'views' => DeliveryNoteListViewSettings::route('/views'),
            'settings' => DeliveryNoteSettings::route('/settings'),
            'view' => ViewDeliveryNote::route('/{record}'),
            'edit' => EditDeliveryNote::route('/{record}/edit'),
        ];
    }
}
