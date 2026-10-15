<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Access\UserType;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Settings\Filament\Resources\Users\UserResource;

/**
 * User form (no descriptions or helper texts by design). Required: 表示名, メールアドレス, ユーザー種別, 在籍状況 and
 * the whole 従業員マスタ情報 (担当者コード, 作業員コード, 部課コード, 従業員カテゴリ — several allowed —, 作業単価,
 * 残業単価). 「パスワードを自動生成」 fills the password and shows it once for copying.
 *
 * Only administrators reach this form (UserResource::canCreate/canEdit) and may only assign the ユーザー種別
 * below them, so 従業員マスタ情報 is administrator-only (SYSTEM_BRAIN APP_02, 11_PERMISSIONS §3).
 * `employee_category_ids` and `generated_password` are not columns (UserAccountService / display only).
 */
final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::accountSection(),
                self::profileSection(),
                self::employeeMasterSection(),
                self::contactSection(),
                Section::make('備考')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(4)
                            ->maxLength(2000),
                    ]),
            ]);
    }

    private static function accountSection(): Section
    {
        return Section::make('アカウント')
            ->icon(Heroicon::OutlinedKey)
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('表示名')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('メールアドレス（ログインID）')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'create' ? '初期パスワード' : '新しいパスワード')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->rule(Password::min(8)->letters()->numbers())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->belowContent(
                        Action::make('generatePassword')
                            ->label('パスワードを自動生成')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->link()
                            ->action(function (Set $set): void {
                                $password = Str::password(12, symbols: false);
                                $set('password', $password);
                                $set('generated_password', $password);
                            }),
                    ),
                TextInput::make('generated_password')
                    ->label('生成したパスワード')
                    ->readOnly()
                    ->copyable(copyMessage: 'コピーしました')
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => filled($get('generated_password'))),
                Select::make('user_type')
                    ->label('ユーザー種別')
                    ->options(fn (): array => UserType::options(UserResource::assignableTypes()))
                    ->default(UserType::Employee->value)
                    ->required()
                    ->in(fn (): array => array_map(fn (UserType $type): string => $type->value, UserResource::assignableTypes()))
                    ->native(false),
                Select::make('employment_status')
                    ->label('在籍状況')
                    ->options(EmploymentStatus::options())
                    ->default(EmploymentStatus::Active->value)
                    ->required()
                    ->native(false),
                CheckboxList::make('dashboards')
                    ->label('利用可能なダッシュボード')
                    ->options(fn (): array => DashboardAccess::grantableOptions())
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function profileSection(): Section
    {
        return Section::make('基本情報')
            ->icon(Heroicon::OutlinedIdentification)
            ->columns(2)
            ->schema([
                TextInput::make('last_name')->label('姓')->maxLength(64),
                TextInput::make('first_name')->label('名')->maxLength(64),
                TextInput::make('last_name_kana')->label('姓（フリガナ）')->maxLength(64),
                TextInput::make('first_name_kana')->label('名（フリガナ）')->maxLength(64),
                Select::make('gender')->label('性別')->options(Gender::options())->native(false),
                TextInput::make('employee_code')
                    ->label('社員番号')
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),
                DatePicker::make('birthday')
                    ->label('生年月日')
                    ->native(false)
                    ->displayFormat('Y年n月j日')
                    ->maxDate(now()),
                DatePicker::make('hired_on')
                    ->label('入社日')
                    ->native(false)
                    ->displayFormat('Y年n月j日'),
                DatePicker::make('left_on')
                    ->label('退職日')
                    ->native(false)
                    ->displayFormat('Y年n月j日')
                    ->afterOrEqual('hired_on'),
            ]);
    }

    private static function employeeMasterSection(): Section
    {
        return Section::make('従業員マスタ情報')
            ->icon(Heroicon::OutlinedBriefcase)
            ->columns(3)
            ->schema([
                TextInput::make('sales_staff_code')
                    ->label('担当者コード')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                TextInput::make('worker_code')
                    ->label('作業員コード')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                TextInput::make('department_code')
                    ->label('部課コード')
                    ->required()
                    ->maxLength(64),
                self::optionSelect('employee_category_ids', '従業員カテゴリ', EmployeeCategory::class, '例：派遣')
                    ->multiple()
                    ->required(),
                self::optionSelect('position_id', '役職', Position::class, '例：課長')
                    ->placeholder('なし'),
                self::rateInput('work_rate', '作業単価'),
                self::rateInput('overtime_rate', '残業単価'),
            ]);
    }

    /**
     * Dropdown over an option list (従業員カテゴリ, 役職) with 「選択肢を追加」 to append a new option.
     *
     * @param  class-string<EmployeeCategory|Position>  $model
     */
    private static function optionSelect(string $name, string $label, string $model, string $example): Select
    {
        $table = (new $model)->getTable();

        return Select::make($name)
            ->label($label)
            ->options(fn (mixed $state): array => $model::options($state))
            ->rule(Rule::exists($table, 'id'))
            ->native(false)
            ->createOptionForm([
                TextInput::make('name')
                    ->label("{$label}名")
                    ->placeholder($example)
                    ->required()
                    ->maxLength(64)
                    ->rule(Rule::unique($table, 'name'))
                    ->validationAttribute("{$label}名"),
            ])
            ->createOptionUsing(fn (array $data): int => (int) $model::createAtEnd((string) $data['name'])->getKey())
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('選択肢を追加')
                ->modalHeading("{$label}を追加")
                ->modalSubmitActionLabel('追加'));
    }

    /** 作業単価 / 残業単価: integer yen per hour, confidential. */
    private static function rateInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->numeric()
            ->integer()
            ->minValue(0)
            ->maxValue(9_999_999)
            ->suffix('円/時');
    }

    private static function contactSection(): Section
    {
        return Section::make('住所・連絡先')
            ->icon(Heroicon::OutlinedMapPin)
            ->columns(2)
            ->collapsible()
            ->schema([
                TextInput::make('postal_code')
                    ->label('郵便番号')
                    ->placeholder('123-4567')
                    ->regex('/^\d{3}-?\d{4}$/')
                    ->maxLength(8),
                TextInput::make('address')
                    ->label('住所')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('phone')->label('電話番号')->tel()->maxLength(32),
                TextInput::make('mobile_phone')->label('携帯電話番号')->tel()->maxLength(32),
                TextInput::make('contact_email')
                    ->label('連絡用メールアドレス')
                    ->email()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('emergency_contact_name')->label('緊急連絡先（氏名・続柄）')->maxLength(64),
                TextInput::make('emergency_contact_phone')->label('緊急連絡先（電話番号）')->tel()->maxLength(32),
            ]);
    }
}
