<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces;

use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages\ManageSpaces;
use Modules\Core\Models\Space;
use UnitEnum;

/**
 * スペース of the current dashboard (permission.md "Space"): name, member employees and apps.
 * Administrators only.
 */
final class SpaceResource extends Resource
{
    protected static ?string $model = Space::class;

    protected static ?string $slug = 'dashboard-settings/spaces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = DashboardSettingsPlugin::NAVIGATION_GROUP;

    protected static ?string $navigationLabel = 'スペース';

    protected static ?string $modelLabel = 'スペース';

    protected static ?string $pluralModelLabel = 'スペース';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 90;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('panel_id', DashboardSettingsPlugin::currentPanelId());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('スペース名')
                    ->placeholder('例：営業スペース')
                    ->required()
                    ->maxLength(64)
                    ->rule(fn (?Space $record): Unique => Rule::unique('spaces', 'name')
                        ->where('panel_id', DashboardSettingsPlugin::currentPanelId())
                        ->ignore($record?->id))
                    ->validationAttribute('スペース名'),
                Select::make('user_ids')
                    ->label('メンバー（従業員）')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => self::employeeOptions())
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $ids = array_map('intval', (array) $value);
                        if (User::query()->whereIn('id', $ids)->where('user_type', UserType::Employee->value)->count() !== count(array_unique($ids))) {
                            $fail('従業員以外のユーザーは選択できません。');
                        }
                    }),
                Select::make('app_ids')
                    ->label('アプリ')
                    ->multiple()
                    ->searchable()
                    ->options(fn (?Space $record): array => SpaceService::placeableApps($record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users')->with('apps'))
            ->columns([
                TextColumn::make('name')
                    ->label('スペース名')
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),
                TextColumn::make('users_count')
                    ->label('メンバー')
                    ->numeric()
                    ->suffix(' 名')
                    ->alignEnd(),
                TextColumn::make('apps.name')
                    ->label('アプリ')
                    ->badge()
                    ->color('info')
                    ->placeholder('なし'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->striped()
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Space $record): array => [
                        ...$data,
                        'user_ids' => $record->users()->pluck('users.id')->all(),
                        'app_ids' => $record->apps()->pluck('id')->all(),
                    ])
                    ->using(fn (Space $record, array $data): Space => self::save($record, $data)),
                DeleteAction::make()
                    ->using(function (Space $record): bool {
                        app(SpaceService::class)->delete($record, self::actorOrFail());

                        return true;
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedRectangleGroup)
            ->emptyStateHeading('スペースがありません');
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?Space $space, array $data): Space
    {
        try {
            return app(SpaceService::class)->save($space, DashboardSettingsPlugin::currentPanelId(), [
                'name' => (string) $data['name'],
                'user_ids' => array_values((array) ($data['user_ids'] ?? [])),
                'app_ids' => array_values((array) ($data['app_ids'] ?? [])),
            ], self::actorOrFail());
        } catch (AppActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt; // keeps the modal open
        }
    }

    /** @return array<int, string> */
    private static function employeeOptions(): array
    {
        return User::query()
            ->with('employeeCategories')
            ->where('user_type', UserType::Employee->value)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->name.($user->employeeCategories->isNotEmpty()
                    ? '（'.$user->employeeCategories->pluck('name')->implode('・').'）'
                    : ''),
            ])
            ->all();
    }

    private static function actorOrFail(): User
    {
        return DashboardSettingsPlugin::actor() ?? throw new AppActionNotAllowed('ログインが必要です。');
    }

    public static function canViewAny(): bool
    {
        return DashboardSettingsPlugin::actorIsAdministrator();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpaces::route('/'),
        ];
    }
}
