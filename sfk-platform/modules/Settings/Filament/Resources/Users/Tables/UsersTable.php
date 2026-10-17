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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Access\UserType;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employeeCategories', 'position']))
            ->columns([
                TextColumn::make('name')
                    ->label('表示名')
                    ->searchable(['name', 'last_name', 'first_name', 'last_name_kana', 'first_name_kana'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('氏名')
                    ->state(fn (User $record): ?string => UserPresenter::fullName($record))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('full_name_kana')
                    ->label('フリガナ')
                    ->state(fn (User $record): ?string => UserPresenter::fullNameKana($record))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employee_code')
                    ->label('社員番号')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sales_staff_code')
                    ->label('担当者コード')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('worker_code')
                    ->label('作業員コード')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department_code')
                    ->label('部課コード')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employeeCategories.name')
                    ->label('従業員カテゴリ')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                // Rates are confidential: the columns exist only for administrators.
                TextColumn::make('work_rate')
                    ->label('作業単価')
                    ->formatStateUsing(fn ($state): string => UserPresenter::hourlyRate($state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn (): bool => EmployeeDataAccess::isAdmin(UserResource::actor()))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overtime_rate')
                    ->label('残業単価')
                    ->formatStateUsing(fn ($state): string => UserPresenter::hourlyRate($state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn (): bool => EmployeeDataAccess::isAdmin(UserResource::actor()))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_type')
                    ->label('ユーザー種別')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => UserPresenter::typeLabel($state))
                    ->color(fn ($state): string => UserPresenter::typeColor($state))
                    ->sortable(),
                TextColumn::make('position.name')
                    ->label('役職')
                    ->placeholder('—')
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
                SelectFilter::make('user_type')
                    ->label('ユーザー種別')
                    ->options(UserType::options())
                    ->multiple(),
                SelectFilter::make('employment_status')
                    ->label('在籍状況')
                    ->options(EmploymentStatus::options())
                    ->multiple(),
                SelectFilter::make('employeeCategories')
                    ->label('従業員カテゴリ')
                    ->relationship('employeeCategories', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                    ->multiple()
                    ->preload(),
                SelectFilter::make('position')
                    ->label('役職')
                    ->relationship('position', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
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
                        ->using(function (User $record): bool {
                            app(UserAccountService::class)->delete($record, UserResource::actor());

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
                        ->visible(fn (): bool => UserResource::canCreate())
                        ->requiresConfirmation()
                        ->modalHeading('選択したユーザーを削除')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $deleted = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                try {
                                    app(UserAccountService::class)->delete($record, UserResource::actor());
                                    $deleted++;
                                } catch (ProtectedAccountException) {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$deleted} 件のユーザーを削除しました")
                                ->body($skipped > 0 ? "{$skipped} 件は削除する権限がないため削除していません。" : null)
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('ユーザーが登録されていません');
    }
}
