<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\AppSettings;

use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Domain\Apps\PermissionSubject;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditAppSettings;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditFieldPermissions;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\EditRecordPermissions;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\Pages\ListAppSettings;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use UnitEnum;

/**
 * アプリの権限 (permission.md "App"): the apps placed in this dashboard's spaces and, per app, who may do what —
 * 全員, a 従業員カテゴリ or one employee, each with 閲覧・追加・編集・削除・ファイル読み込み・ファイル書き出し.
 * 個人 overrides 従業員カテゴリ, which overrides 全員 (AppAccess). Per app also the レコード (EditRecordPermissions,
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
                    ]),
                Section::make('アクセス権')
                    ->schema([
                        Repeater::make('permissions')
                            ->hiddenLabel()
                            ->relationship(modifyQueryUsing: fn (Builder $query) => $query
                                ->orderByRaw("case subject_type when 'user' then 1 when 'category' then 2 else 3 end")
                                ->orderBy('id'))
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('権限を追加')
                            ->itemLabel(fn (array $state): string => AccessEntryFields::subjectLabel($state))
                            ->extraAttributes(['class' => 'sfk-access'])
                            ->columns(6)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $everyone = array_filter((array) $value, fn (mixed $item): bool => is_array($item)
                                    && ($item['subject_type'] ?? null) === PermissionSubject::Everyone->value);
                                if (count($everyone) > 1) {
                                    $fail('「全員」の権限は1つだけ設定できます。');
                                }
                            })
                            ->schema([
                                Select::make('subject_type')
                                    ->label('対象')
                                    ->options(PermissionSubject::options())
                                    ->default(PermissionSubject::Everyone->value)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->columnSpan(2),
                                Select::make('employee_category_id')
                                    ->live()
                                    ->label('従業員カテゴリ')
                                    ->options(fn (mixed $state): array => EmployeeCategory::options($state))
                                    ->visible(fn (Get $get): bool => $get('subject_type') === PermissionSubject::Category->value)
                                    ->required(fn (Get $get): bool => $get('subject_type') === PermissionSubject::Category->value)
                                    ->distinct()
                                    ->native(false)
                                    ->columnSpan(4),
                                Select::make('user_id')
                                    ->live()
                                    ->label('従業員')
                                    ->options(fn (): array => User::query()
                                        ->where('user_type', UserType::Employee->value)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->visible(fn (Get $get): bool => $get('subject_type') === PermissionSubject::User->value)
                                    ->required(fn (Get $get): bool => $get('subject_type') === PermissionSubject::User->value)
                                    ->distinct()
                                    ->searchable()
                                    ->columnSpan(4),
                                AccessEntryFields::rights(array_map(
                                    fn (AppAbility $ability): Checkbox => Checkbox::make($ability->column())
                                        ->label($ability->label()),
                                    AppAbility::cases(),
                                ))->columnSpanFull(),
                            ]),
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

    /** The app's spaces of this dashboard (an app may be in several). */
    private static function spaceNames(BusinessApp $app): string
    {
        return $app->spaces->where('panel_id', DashboardSettingsPlugin::currentPanelId())->pluck('name')->implode('、');
    }
}
