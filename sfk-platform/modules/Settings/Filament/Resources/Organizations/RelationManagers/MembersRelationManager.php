<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Organizations\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\OrganizationMembership;
use Modules\Settings\Filament\Resources\Users\UserPresenter;
use Modules\Settings\Filament\Resources\Users\UserResource;

/** Read-only list of the organization's members. Assignments are made on the user page. */
final class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = '所属メンバー';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('メンバーの割り当てと役職の変更は「ユーザー管理」の各ユーザーの編集画面で行います。')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'position']))
            ->columns([
                TextColumn::make('user.name')->label('表示名')->description(fn (OrganizationMembership $record): string => $record->user->email)->searchable(),
                TextColumn::make('position.name')->label('役職')->placeholder('役職なし'),
                IconColumn::make('is_primary')->label('主所属')->boolean()->alignCenter(),
                TextColumn::make('user.employment_status')
                    ->label('在籍状況')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => UserPresenter::statusLabel($state))
                    ->color(fn ($state): string => UserPresenter::statusColor($state)),
            ])
            ->recordActions([
                Action::make('editUser')
                    ->label('ユーザーを編集')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (OrganizationMembership $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                    ->visible(fn (OrganizationMembership $record): bool => UserResource::canEdit($record->user)),
            ])
            ->emptyStateHeading('所属メンバーはいません');
    }
}
