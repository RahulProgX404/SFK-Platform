<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Organization;
use Modules\Settings\Filament\NavigationGroup;
use Modules\Settings\Filament\Resources\Organizations\Pages\CreateOrganization;
use Modules\Settings\Filament\Resources\Organizations\Pages\EditOrganization;
use Modules\Settings\Filament\Resources\Organizations\Pages\ListOrganizations;
use Modules\Settings\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use Modules\Settings\Filament\Resources\Organizations\RelationManagers\PositionsRelationManager;
use UnitEnum;

/**
 * 組織管理: organizations (nestable) and, on each organization, its positions (役職).
 * Users are assigned to organizations/positions on the user edit page.
 */
final class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Organizations->value;

    protected static ?string $navigationLabel = '組織・役職';

    protected static ?string $modelLabel = '組織';

    protected static ?string $pluralModelLabel = '組織';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('組織情報')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('組織名')
                            ->placeholder('例：営業部')
                            ->required()
                            ->maxLength(128),
                        TextInput::make('code')
                            ->label('組織コード')
                            ->helperText('旧システムの部課コードなど（任意）')
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),
                        Select::make('parent_id')
                            ->label('上位組織')
                            ->placeholder('なし（最上位）')
                            ->options(fn (?Organization $record): array => Organization::query()
                                ->when($record !== null, fn (Builder $query) => $query
                                    ->whereKeyNot($record->getKey())
                                    ->whereNotIn('id', $record->descendantIds()))
                                ->ordered()
                                ->pluck('name', 'id')
                                ->all())
                            ->rule(fn (?Organization $record) => $record === null
                                ? 'nullable'
                                : Rule::notIn([$record->getKey(), ...$record->descendantIds()]))
                            ->searchable(),
                        TextInput::make('sort_order')
                            ->label('表示順')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('有効')
                            ->helperText('無効にした組織は新しい所属の選択肢に表示されません。')
                            ->default(true),
                        Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount(['positions', 'memberships']))
            ->columns([
                TextColumn::make('code')
                    ->label('組織コード')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('組織名')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Organization $record): ?string => $record->parent !== null ? "上位：{$record->parent->name}" : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('positions_count')
                    ->label('役職数')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('memberships_count')
                    ->label('所属人数')
                    ->numeric()
                    ->suffix(' 名')
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_active')->label('有効'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('この組織と、その役職・所属情報をすべて削除します。下位組織は最上位の組織になります。この操作は元に戻せません。'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->emptyStateHeading('組織が登録されていません')
            ->emptyStateDescription('「新規作成」から組織を登録し、組織ごとに役職を追加してください。');
    }

    public static function getRelations(): array
    {
        return [
            PositionsRelationManager::class,
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
