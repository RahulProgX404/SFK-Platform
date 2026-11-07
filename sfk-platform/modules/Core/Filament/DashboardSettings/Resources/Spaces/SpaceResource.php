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
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
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
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Filament\DashboardSettings\Resources\AppSettings\AccessEntryFields;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\Pages\ManageSpaces;
use Modules\Core\Models\Space;
use Modules\Core\Models\SpacePermission;
use UnitEnum;

/**
 * スペース of the current dashboard (permission.md "Space"): name, member employees, apps and the space's
 * アクセス権 (rows for 全員 / 従業員カテゴリ / 個人: what its members get in all its apps, added to the apps' own
 * permissions — AppAccess).
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
                    ->live()
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
                // スペースの権限: what the members get in every app of the space (個人 rows: members only)
                Fieldset::make('アクセス権')
                    ->columns(1)
                    ->schema([
                        AccessEntryFields::permissionRows(fn (Get $get): array => array_intersect_key(
                            AccessEntryFields::employees(),
                            array_flip(array_map('intval', (array) $get('../../user_ids'))),
                        )),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users')->with(['apps', 'permissions.user', 'permissions.employeeCategory']))
            ->columns([
                TextColumn::make('name')
                    ->label('スペース名')
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
                TextColumn::make('rights')
                    ->label('アクセス権')
                    ->state(fn (Space $record): array => self::rightLines($record))
                    ->badge()
                    ->color('success')
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
                        'app_ids' => $record->apps()->pluck('business_apps.id')->all(),
                        'permissions' => $record->permissions()->get()
                            ->map(fn (SpacePermission $row): array => [
                                'subject_type' => $row->subject_type->value,
                                'employee_category_id' => $row->employee_category_id,
                                'user_id' => $row->user_id,
                                ...$row->only(AppAbility::columns()),
                            ])
                            ->all(),
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
                'permissions' => array_values(array_filter((array) ($data['permissions'] ?? []), 'is_array')),
            ], self::actorOrFail());
        } catch (AppActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt; // keeps the modal open
        }
    }

    /**
     * The rows of the space's アクセス権, e.g. 「全員：閲覧・編集」「従業員カテゴリ：派遣：閲覧」.
     *
     * @return list<string>
     */
    private static function rightLines(Space $space): array
    {
        return $space->permissions
            ->map(fn (SpacePermission $row): string => $row->subjectLabel().'：'.($row->abilities() === []
                ? 'なし'
                : implode('・', array_map(fn (AppAbility $ability): string => $ability->label(), $row->abilities()))))
            ->values()
            ->all();
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
