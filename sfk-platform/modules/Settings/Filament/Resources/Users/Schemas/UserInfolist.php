<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Http\Portal\DashboardDirectory;
use Modules\Core\Http\Portal\DashboardEntry;
use Modules\Settings\Filament\Resources\Users\UserPresenter;
use Modules\Settings\Filament\Resources\Users\UserResource;

final class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('アカウント')
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('name')->label('表示名')->weight('bold'),
                        TextEntry::make('email')->label('メールアドレス（ログインID）')->copyable(),
                        TextEntry::make('employment_status')
                            ->label('在籍状況')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => UserPresenter::statusLabel($state))
                            ->color(fn ($state): string => UserPresenter::statusColor($state)),
                        TextEntry::make('user_type')
                            ->label('ユーザー種別')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => UserPresenter::typeLabel($state))
                            ->color(fn ($state): string => UserPresenter::typeColor($state)),
                        TextEntry::make('dashboards')
                            ->label('利用可能なダッシュボード')
                            ->state(fn (User $record): array => array_map(
                                fn (DashboardEntry $entry): string => $entry->label,
                                app(DashboardDirectory::class)->for($record),
                            ))
                            ->badge()
                            ->color('info')
                            ->placeholder('なし（従業員カテゴリで設定します）'),
                    ]),
                Section::make('ログイン状況')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columnSpan(1)
                    ->schema([
                        IconEntry::make('must_change_password')
                            ->label('パスワード変更待ち')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedClock)
                            ->falseIcon(Heroicon::OutlinedCheckCircle)
                            ->trueColor('warning')
                            ->falseColor('success'),
                        TextEntry::make('last_login_at')->label('最終ログイン')->dateTime('Y/m/d H:i')->placeholder('未ログイン'),
                        TextEntry::make('password_changed_at')->label('パスワード変更日時')->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('managed_by_env')
                            ->label('管理方法')
                            ->state(fn (User $record): string => $record->managed_by_env ? '環境変数（スーパー管理者）' : 'ユーザー管理'),
                    ]),
                Section::make('基本情報')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('氏名')
                            ->state(fn (User $record): ?string => UserPresenter::fullName($record))
                            ->placeholder('—'),
                        TextEntry::make('full_name_kana')
                            ->label('フリガナ')
                            ->state(fn (User $record): ?string => UserPresenter::fullNameKana($record))
                            ->placeholder('—'),
                        TextEntry::make('employee_code')->label('社員番号')->placeholder('—'),
                        TextEntry::make('gender')
                            ->label('性別')
                            ->formatStateUsing(fn (Gender $state): string => $state->label())
                            ->placeholder('—'),
                        TextEntry::make('birthday')->label('生年月日')->date('Y年n月j日')->placeholder('—'),
                        TextEntry::make('hired_on')->label('入社日')->date('Y年n月j日')->placeholder('—'),
                        TextEntry::make('left_on')->label('退職日')->date('Y年n月j日')->placeholder('—'),
                    ]),
                Section::make('従業員マスタ情報')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('sales_staff_code')->label('担当者コード')->placeholder('—'),
                        TextEntry::make('worker_code')->label('作業員コード')->placeholder('—'),
                        TextEntry::make('department_code')->label('部課コード')->placeholder('—'),
                        TextEntry::make('employeeCategories.name')->label('従業員カテゴリ')->badge()->color('gray')->placeholder('—'),
                        TextEntry::make('position.name')->label('役職')->placeholder('—'),
                        self::rateEntry('work_rate', '作業単価'),
                        self::rateEntry('overtime_rate', '残業単価'),
                    ]),
                Section::make('住所・連絡先')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('postal_code')->label('郵便番号')->placeholder('—'),
                        TextEntry::make('address')->label('住所')->placeholder('—')->columnSpan(2),
                        TextEntry::make('phone')->label('電話番号')->placeholder('—'),
                        TextEntry::make('mobile_phone')->label('携帯電話番号')->placeholder('—'),
                        TextEntry::make('contact_email')->label('連絡用メールアドレス')->placeholder('—'),
                        TextEntry::make('emergency_contact_name')->label('緊急連絡先（氏名・続柄）')->placeholder('—'),
                        TextEntry::make('emergency_contact_phone')->label('緊急連絡先（電話番号）')->placeholder('—'),
                    ]),
                Section::make('備考')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel()->placeholder('—'),
                    ]),
            ]);
    }

    /** 作業単価 / 残業単価 are shown only to administrators and the user themselves (EmployeeDataAccess). */
    private static function rateEntry(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->formatStateUsing(fn ($state): string => UserPresenter::hourlyRate($state))
            ->placeholder('—')
            ->visible(fn (User $record): bool => EmployeeDataAccess::canViewRates(UserResource::actor(), $record));
    }
}
