<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditFieldPermissions;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditRecordPermissions;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\ListAppSettings;
use Modules\Core\Models\BusinessApp;
use UnitEnum;

/**
 * アプリの権限 (permission.md "App"): the apps placed in this dashboard's spaces and, per app, who may do what —
 * 全員, a 従業員カテゴリ or one employee, each with 閲覧・追加・編集・削除・ファイル読み込み・ファイル書き出し.
 * 個人 overrides 従業員カテゴリ, which overrides 全員; the アクセス権 of the app's spaces (スペースの権限, shown read-only,
 * set on スペース) are added to that for their members (AppAccess). Per app also the レコード (EditRecordPermissions,
 * RecordAccess) and フィールド (EditFieldPermissions, FieldAccess) アクセス権. Administrators only.
 */
final class AppSettingsResource extends Resource
{
    protected static ?string $model = BusinessApp::class;

    protected static ?string $slug = 'dashboard-settings/apps';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = DashboardSettingsPlugin::NAVIGATION_GROUP;

    protected static ?string $navigationLabel = 'アプリの権限';

    protected static ?string $modelLabel = 'アプリ';

    protected static ?string $pluralModelLabel = 'アプリ';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 91;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('spaces', fn (Builder $query) => $query->where('panel_id', DashboardSettingsPlugin::currentPanelId()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('アプリ')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')->label('アプリ名')->weight(FontWeight::SemiBold),
                        TextEntry::make('code')->label('コード'),
                        TextEntry::make('space_names')->label('スペース')->state(fn (BusinessApp $record): string => self::spaceNames($record)),
                        TextEntry::make('status')
                            ->label('状態')
                            ->badge()
                            ->formatStateUsing(fn (AppStatus $state): string => $state->label()),
                        TextEntry::make('space_rights')
                            ->label('スペースの権限')
                            ->state(fn (BusinessApp $record): array => self::spaceRights($record))
                            ->badge()
                            ->color('success')
                            ->placeholder('なし')
                            ->columnSpanFull(),
                    ]),
                Section::make('アクセス権')
                    ->schema([
                        AccessEntryFields::permissionRows(fn (): array => AccessEntryFields::employees())
                            ->relationship(modifyQueryUsing: fn (Builder $query) => $query
                                ->orderByRaw("case subject_type when 'user' then 1 when 'category' then 2 else 3 end")
                                ->orderBy('id')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('spaces')->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->label('アプリ名')
                    ->searchable(['name', 'code']),
                TextColumn::make('space_names')->label('スペース')->state(fn (BusinessApp $record): string => self::spaceNames($record)),
                TextColumn::make('status')
                    ->label('状態')
                    ->badge()
                    ->formatStateUsing(fn (AppStatus $state): string => $state->label())
                    ->color(fn (AppStatus $state): string => self::statusColor($state)),
                TextColumn::make('permissions_count')
                    ->label('権限の行数')
                    ->numeric()
                    ->alignEnd(),
            ])
            ->defaultSort('sort_order')
            ->paginated(false)
            ->striped()
            ->recordActions([
                EditAction::make()->label('アプリ'),
                Action::make('records')
                    ->label('レコード')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->url(fn (BusinessApp $record): string => self::getUrl('records', ['record' => $record])),
                Action::make('fields')
                    ->label('フィールド')
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->url(fn (BusinessApp $record): string => self::getUrl('fields', ['record' => $record])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedLockClosed)
            ->emptyStateHeading('このダッシュボードに配置されたアプリはありません');
    }

    public static function statusColor(AppStatus $status): string
    {
        return match ($status) {
            AppStatus::Published => 'success',
            AppStatus::Maintenance => 'warning',
            AppStatus::Stopped => 'danger',
            AppStatus::Ready => 'info',
            default => 'gray',
        };
    }

    public static function canViewAny(): bool
    {
        return DashboardSettingsPlugin::actorIsAdministrator();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppSettings::route('/'),
            'edit' => EditAppSettings::route('/{record}/edit'),
            'records' => EditRecordPermissions::route('/{record}/records'),
            'fields' => EditFieldPermissions::route('/{record}/fields'),
        ];
    }

    /**
     * The rows of the アクセス権 of the app's spaces of this dashboard (added to the rows below for the spaces'
     * members by AppAccess), e.g. 「営業（全員）：閲覧・編集」「営業（従業員カテゴリ：派遣）：閲覧」.
     *
     * @return list<string>
     */
    public static function spaceRights(BusinessApp $app): array
    {
        $lines = [];
        $spaces = $app->spaces->where('panel_id', DashboardSettingsPlugin::currentPanelId());

        foreach ($spaces as $space) {
            foreach ($space->permissions()->with(['user', 'employeeCategory'])->get() as $row) {
                $abilities = $row->abilities();
                $lines[] = "{$space->name}（{$row->subjectLabel()}）：".($abilities === []
                    ? 'なし'
                    : implode('・', array_map(fn (AppAbility $ability): string => $ability->label(), $abilities)));
            }
        }

        return $lines;
    }

    /** The app's spaces of this dashboard (an app may be in several). */
    private static function spaceNames(BusinessApp $app): string
    {
        return $app->spaces->where('panel_id', DashboardSettingsPlugin::currentPanelId())->pluck('name')->implode('、');
    }
}
