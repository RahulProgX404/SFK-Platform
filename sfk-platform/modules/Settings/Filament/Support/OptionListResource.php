<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\Resources\Users\UserResource;

/**
 * Screen for a user-maintained dropdown list (従業員カテゴリ, 役職): add, rename, reorder by drag, (de)activate.
 * Administrators only manage it; an option in use cannot be deleted — deactivate it instead.
 */
abstract class OptionListResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    /** Name of one option in the UI, e.g. 「カテゴリ」, 「役職」. */
    abstract public static function optionLabel(): string;

    abstract public static function optionExample(): string;

    /**
     * Appends a new option at the end of the order (form data beyond name / is_active is filled too).
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function createOption(array $data): EmployeeCategory|Position;

    /** @return list<Component> extra fields after the name (e.g. 利用可能なダッシュボード) */
    protected static function extraFormComponents(): array
    {
        return [];
    }

    /** @return list<Column> extra columns after the name */
    protected static function extraColumns(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
    {
        $label = static::optionLabel();

        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label("{$label}名")
                    ->placeholder(static::optionExample())
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->validationAttribute("{$label}名"),
                ...static::extraFormComponents(),
                Toggle::make('is_active')
                    ->label('有効')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        $label = static::optionLabel();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users'))
            ->columns([
                TextColumn::make('name')
                    ->label("{$label}名")
                    ->searchable(),
                ...static::extraColumns(),
                TextColumn::make('users_count')
                    ->label('ユーザー数')
                    ->numeric()
                    ->suffix(' 名')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order', fn (): bool => static::canManage())
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => static::canManage()),
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => static::canDelete($record)),
            ])
            ->emptyStateIcon(Heroicon::OutlinedTag)
            ->emptyStateHeading("{$label}が登録されていません");
    }

    /** Only administrators add, rename, reorder or delete options. */
    public static function canManage(): bool
    {
        return EmployeeDataAccess::isAdmin(UserResource::actor());
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage()
            && ($record instanceof EmployeeCategory || $record instanceof Position)
            && ! $record->users()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
