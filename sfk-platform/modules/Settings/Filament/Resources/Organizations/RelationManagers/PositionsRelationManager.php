<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/** 役職 of one organization. */
final class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';

    protected static ?string $title = '役職';

    protected static ?string $modelLabel = '役職';

    protected static ?string $pluralModelLabel = '役職';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('役職名')
                    ->placeholder('例：部長')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule
                        ->where('organization_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('code')
                    ->label('役職コード')
                    ->maxLength(32),
                TextInput::make('rank')
                    ->label('職位ランク')
                    ->helperText('数値が大きいほど上位の役職です（例：部長 50、課長 40、主任 20）。')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(999)
                    ->default(0),
                TextInput::make('sort_order')
                    ->label('表示順')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('memberships'))
            ->columns([
                TextColumn::make('name')->label('役職名')->weight(FontWeight::SemiBold)->searchable(),
                TextColumn::make('code')->label('役職コード')->placeholder('—'),
                TextColumn::make('rank')->label('職位ランク')->numeric()->sortable()->alignEnd(),
                TextColumn::make('memberships_count')->label('在任者数')->numeric()->suffix(' 名')->alignEnd(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label('役職を追加')->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('この役職を削除します。この役職のユーザーは「役職なし」の所属になります。'),
            ])
            ->emptyStateHeading('役職が登録されていません')
            ->emptyStateDescription('「役職を追加」から、この組織の役職を登録してください。');
    }
}
