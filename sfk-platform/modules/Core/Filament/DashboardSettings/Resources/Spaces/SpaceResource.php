<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
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
 * スペース of the current dashboard (permission.md "Space"): name, members (employees by name and by 従業員カテゴリ —
 * SpaceMembership), apps and the space's アクセス権 (rows for 全員 / 従業員カテゴリ / 個人: what its members get in all
 * its apps, added to the apps' own permissions — AppAccess).
 * スペースを作成 and ✎ open SpaceWizard (user, 2026-11-11: a quick entry — スペース名, メンバー, アプリ, check); アクセス権 has
 * its own dialog. Administrators only.
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users')->with(['apps', 'employeeCategories', 'permissions.user', 'permissions.employeeCategory']))
            ->columns([
                TextColumn::make('name')
                    ->label('スペース名')
                    ->searchable(),
                TextColumn::make('users_count')
                    ->label('メンバー')
                    ->numeric()
                    ->suffix(' 名')
                    ->alignEnd(),
                TextColumn::make('employeeCategories.name')
                    ->label('従業員カテゴリ')
                    ->badge()
                    ->color('gray')
                    ->placeholder('なし'),
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
                // name, members and apps: the quick entry over the screen
                Action::make('edit')
                    ->label('編集')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->action(fn (Space $record, Component $livewire) => $livewire->dispatch(SpaceWizard::OPEN_EVENT, space: $record->id)),
                Action::make('permissions')
                    ->label('アクセス権')
                    ->icon(Heroicon::OutlinedKey)
                    ->modalHeading(fn (Space $record): string => $record->name.'：アクセス権')
                    ->modalSubmitActionLabel('保存')
                    ->fillForm(fn (Space $record): array => ['permissions' => $record->permissions()->get()
                        ->map(fn (SpacePermission $row): array => [
                            'subject_type' => $row->subject_type->value,
                            'employee_category_id' => $row->employee_category_id,
                            'user_id' => $row->user_id,
                            ...$row->only(AppAbility::columns()),
                        ])
                        ->all()])
                    // スペースの権限: what the members get in every app of the space (個人 rows: members only)
                    ->schema(fn (Space $record): array => [
                        Fieldset::make('アクセス権')
                            ->columns(1)
                            ->schema([AccessEntryFields::permissionRows(fn (): array => self::memberOptions($record))]),
                    ])
                    ->action(fn (Space $record, array $data) => self::savePermissions($record, $data)),
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
    public static function savePermissions(Space $space, array $data): void
    {
        try {
            app(SpaceService::class)->savePermissions($space, array_values(array_filter((array) ($data['permissions'] ?? []), 'is_array')), self::actorOrFail());
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

    /** @return array<int, string> the members of the space (個人 rows), by name */
    private static function memberOptions(Space $space): array
    {
        return $space->users()
            ->where('user_type', UserType::Employee->value)
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
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
