<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Application\Users\ProtectedAccountException;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Settings\Filament\Resources\Users\Actions\ResetPasswordAction;
use Modules\Settings\Filament\Resources\Users\UserPresenter;
use Modules\Settings\Filament\Resources\Users\UserResource;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'organizations']))
            ->columns([
                TextColumn::make('name')
                    ->label('表示名')
                    ->description(fn (User $record): string => $record->email)
                    ->weight(FontWeight::SemiBold)
                    ->searchable(['name', 'email', 'last_name', 'first_name', 'last_name_kana', 'first_name_kana'])
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('氏名')
                    ->state(fn (User $record): ?string => UserPresenter::fullName($record))
                    ->description(fn (User $record): ?string => UserPresenter::fullNameKana($record))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('employee_code')
                    ->label('社員番号')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('ロール')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => UserPresenter::roleLabel($state))
                    ->placeholder('—'),
                TextColumn::make('organizations.name')
                    ->label('所属組織')
                    ->badge()
                    ->color('info')
                    ->placeholder('未所属')
                    ->toggleable(),
                TextColumn::make('employment_status')
                    ->label('在籍状況')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => UserPresenter::statusLabel($state))
                    ->color(fn ($state): string => UserPresenter::statusColor($state))
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('最終ログイン')
                    ->since()
                    ->dateTimeTooltip('Y/m/d H:i')
                    ->placeholder('未ログイン')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('登録日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('employment_status')
                    ->label('在籍状況')
                    ->options(EmploymentStatus::options())
                    ->multiple(),
                SelectFilter::make('roles')
                    ->label('ロール')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('organizations')
                    ->label('所属組織')
                    ->relationship('organizations', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('must_change_password')
                    ->label('パスワード変更待ち')
                    ->trueLabel('変更待ちのみ')
                    ->falseLabel('変更済みのみ'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (User $record): bool => UserResource::canEdit($record)),
                ActionGroup::make([
                    ResetPasswordAction::make(),
                    DeleteAction::make()
                        ->visible(fn (User $record): bool => UserResource::canDelete($record))
                        ->modalDescription('このユーザーを削除します。所属情報も削除され、元に戻せません。退職者は削除せず「在籍状況」を変更することをおすすめします。')
                        ->using(function (User $record): bool {
                            app(UserAccountService::class)->delete($record, self::actor());

                            return true;
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('選択したユーザーを削除')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('選択したユーザーを削除')
                        ->modalDescription('スーパー管理者と自分自身のアカウントは削除されません。この操作は元に戻せません。')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $deleted = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                try {
                                    app(UserAccountService::class)->delete($record, self::actor());
                                    $deleted++;
                                } catch (ProtectedAccountException) {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$deleted} 件のユーザーを削除しました")
                                ->body($skipped > 0 ? "{$skipped} 件は保護されたアカウントのため削除していません。" : null)
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('ユーザーが登録されていません')
            ->emptyStateDescription('「新規作成」からユーザーを登録してください。');
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
