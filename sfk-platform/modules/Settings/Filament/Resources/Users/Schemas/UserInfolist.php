<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Models\OrganizationMembership;
use Modules\Settings\Filament\Resources\Users\UserPresenter;

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
                        TextEntry::make('roles.name')
                            ->label('ロール')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (string $state): string => UserPresenter::roleLabel($state))
                            ->placeholder('—'),
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
                        TextEntry::make('employee_category')
                            ->label('従業員区分')
                            ->formatStateUsing(fn ($state): string => UserPresenter::categoryLabel($state))
                            ->placeholder('—'),
                        TextEntry::make('birthday')->label('生年月日')->date('Y年n月j日')->placeholder('—'),
                        TextEntry::make('hired_on')->label('入社日')->date('Y年n月j日')->placeholder('—'),
                        TextEntry::make('left_on')->label('退職日')->date('Y年n月j日')->placeholder('—'),
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
                Section::make('所属組織・役職')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('memberships')
                            ->hiddenLabel()
                            ->state(fn (User $record): array => $record->organizationMemberships()
                                ->with(['organization', 'position'])
                                ->get()
                                ->map(fn (OrganizationMembership $membership): string => $membership->organization->name
                                    .($membership->position !== null ? ' ／ '.$membership->position->name : '')
                                    .($membership->is_primary ? '（主所属）' : ''))
                                ->all())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('所属組織はありません'),
                    ]),
                Section::make('備考')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel()->placeholder('—'),
                    ]),
            ]);
    }
}
