<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Schemas;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Access\Roles;
use Modules\Core\Domain\People\EmployeeCategory;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Position;
use Spatie\Permission\Models\Role;

/**
 * User form. Required: 表示名, メールアドレス, ロール, 在籍状況 — everything else is optional.
 * `roles` and `panels` are not columns; the Create/Edit pages pass them to UserAccountService.
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
                self::contactSection(),
                self::membershipSection(),
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
            ->description('ログイン情報と利用権限')
            ->icon(Heroicon::OutlinedKey)
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('表示名')
                    ->helperText('画面に表示される名前です。')
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
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? '空欄の場合は仮パスワードを自動発行します。ユーザーは初回ログイン時にパスワードの変更が必要です。'
                        : '変更する場合のみ入力してください。変更後、ユーザーは次回ログイン時にパスワードの変更が必要になります。'),
                Select::make('employment_status')
                    ->label('在籍状況')
                    ->options(EmploymentStatus::options())
                    ->default(EmploymentStatus::Active->value)
                    ->required()
                    ->native(false)
                    ->helperText('退職・定年退職・解雇のユーザーはログインできなくなります。'),
                TagsInput::make('roles')
                    ->label('ロール')
                    ->placeholder('ロール名を入力して Enter')
                    ->suggestions(fn (): array => Role::query()
                        ->whereNotIn('name', Roles::RESERVED)
                        ->orderBy('name')
                        ->pluck('name')
                        ->all())
                    ->required()
                    ->nestedRecursiveRules([
                        'max:64',
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (Roles::isReserved((string) $value)) {
                                $fail("「{$value}」は予約されたロールのため割り当てできません。");
                            }
                        },
                    ])
                    ->helperText('例：経理管理者、営業、製造。新しいロール名を入力すると自動で作成されます。')
                    ->columnSpanFull(),
                CheckboxList::make('panels')
                    ->label('利用可能なダッシュボード')
                    ->options(fn (): array => self::panelOptions())
                    ->columns(2)
                    ->helperText('ログイン後のダッシュボード選択画面に表示されます。')
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
                TextInput::make('employee_code')
                    ->label('社員番号')
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),
                Select::make('employee_category')
                    ->label('従業員区分')
                    ->options(EmployeeCategory::options())
                    ->native(false),
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
                    ->placeholder('都道府県から入力')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('phone')->label('電話番号')->tel()->maxLength(32),
                TextInput::make('mobile_phone')->label('携帯電話番号')->tel()->maxLength(32),
                TextInput::make('contact_email')
                    ->label('連絡用メールアドレス')
                    ->helperText('ログイン用とは別の連絡先がある場合に入力します。')
                    ->email()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('emergency_contact_name')->label('緊急連絡先（氏名・続柄）')->maxLength(64),
                TextInput::make('emergency_contact_phone')->label('緊急連絡先（電話番号）')->tel()->maxLength(32),
            ]);
    }

    private static function membershipSection(): Section
    {
        return Section::make('所属組織・役職')
            ->description('複数の組織に所属できます。主所属は1つだけ選択できます。')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->schema([
                Repeater::make('organizationMemberships')
                    ->hiddenLabel()
                    ->relationship()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('所属を追加')
                    ->columns(3)
                    ->schema([
                        Select::make('organization_id')
                            ->label('組織')
                            ->options(fn (mixed $state): array => Organization::query()
                                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $state))
                                ->ordered()
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->live()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(fn (Set $set) => $set('position_id', null)),
                        Select::make('position_id')
                            ->label('役職')
                            ->placeholder(fn (Get $get): string => filled($get('organization_id')) ? '役職を選択' : '先に組織を選択してください')
                            ->options(fn (Get $get): array => filled($get('organization_id'))
                                ? Position::query()
                                    ->where('organization_id', $get('organization_id'))
                                    ->orderBy('sort_order')
                                    ->orderByDesc('rank')
                                    ->pluck('name', 'id')
                                    ->all()
                                : [])
                            ->disabled(fn (Get $get): bool => blank($get('organization_id')))
                            ->rule(fn (Get $get): Exists => Rule::exists('positions', 'id')
                                ->where('organization_id', $get('organization_id'))),
                        Toggle::make('is_primary')
                            ->label('主所属')
                            ->inline(false)
                            ->fixIndistinctState(),
                    ]),
            ]);
    }

    /** @return array<string, string> panel id => card label */
    private static function panelOptions(): array
    {
        $options = [];
        foreach (Filament::getPanels() as $id => $panel) {
            $options[$id] = (string) (config("{$id}.portal.label") ?? $panel->getBrandName());
        }

        return $options;
    }
}
