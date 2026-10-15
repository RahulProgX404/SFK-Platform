<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\AppResource;
use Modules\Erp\Filament\NavigationGroup;
use Modules\MasterData\Filament\Resources\Partners\Pages\CreatePartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\EditPartner;
use Modules\MasterData\Filament\Resources\Partners\Pages\ListPartners;
use Modules\MasterData\Filament\Resources\Partners\Pages\ViewPartner;
use Modules\MasterData\Filament\Resources\Partners\Schemas\PartnerForm;
use Modules\MasterData\Filament\Resources\Partners\Schemas\PartnerInfolist;
use Modules\MasterData\Filament\Resources\Partners\Tables\PartnersTable;
use Modules\MasterData\Models\Partner;
use UnitEnum;

/**
 * App 01. 取引先マスタ（得意先・仕入先） (SYSTEM_BRAIN APP_01, legacy app 6) in the 白石封筒 dashboard. Access follows
 * the app's permissions and status (AppResource): it appears on アプリ設定 as 開発中, an administrator places it in
 * a space, and employees then use it per 閲覧・追加・編集・削除・ファイル書き出し.
 */
final class PartnerResource extends AppResource
{
    protected static ?string $model = Partner::class;

    protected static ?string $slug = 'partners';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData->value;

    protected static ?string $navigationLabel = '取引先マスタ';

    protected static ?string $modelLabel = '取引先';

    protected static ?string $pluralModelLabel = '取引先';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function appCode(): string
    {
        return 'partner-master';
    }

    public static function appName(): string
    {
        return '01. 取引先マスタ（得意先・仕入先）';
    }

    /** SYSTEM_BRAIN APP_01 README (purpose of the app). */
    public static function appDescription(): string
    {
        return "得意先マスタアプリ\n得意先の情報を登録・管理するマスタアプリです。（得意先マスタと仕入先マスタを統一させています）\n"
            .'仕入先や外注先に該当する得意先も、このアプリで一元管理します。';
    }

    public static function form(Schema $schema): Schema
    {
        return PartnerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartnerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnersTable::configure($table);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_kana', 'short_name', 'code'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'view' => ViewPartner::route('/{record}'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
