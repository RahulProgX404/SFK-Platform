<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Profile;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Domain\People\Gender;
use SensitiveParameter;

/**
 * プロフィール (user menu of every dashboard): the user's own details. They may update 氏名, フリガナ, 性別 and
 * 住所・連絡先 and change their password here; everything else is maintained by administrators.
 */
final class ProfilePage extends EditProfile
{
    /** Attributes the user may change themselves. */
    private const EDITABLE = [
        'last_name', 'first_name', 'last_name_kana', 'first_name_kana', 'gender',
        'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    public static function getLabel(): string
    {
        return 'プロフィール';
    }

    public function getTitle(): string
    {
        return 'プロフィール';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('アカウント')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('表示名'),
                    TextEntry::make('email')->label('メールアドレス（ログインID）'),
                    TextEntry::make('employee_code')->label('社員番号')->placeholder('—'),
                    TextEntry::make('user_type')
                        ->label('ユーザー種別')
                        ->state(fn (): string => $this->user()->user_type->label()),
                    TextEntry::make('categories')
                        ->label('従業員カテゴリ')
                        ->state(fn (): array => $this->user()->employeeCategories()->pluck('name')->all())
                        ->badge()
                        ->color('gray')
                        ->placeholder('—'),
                    TextEntry::make('position')
                        ->label('役職')
                        ->state(fn (): ?string => $this->user()->position?->name)
                        ->placeholder('—'),
                ]),
            Section::make('基本情報')
                ->columns(2)
                ->schema([
                    TextInput::make('last_name')->label('姓')->maxLength(64),
                    TextInput::make('first_name')->label('名')->maxLength(64),
                    TextInput::make('last_name_kana')->label('姓（フリガナ）')->maxLength(64),
                    TextInput::make('first_name_kana')->label('名（フリガナ）')->maxLength(64),
                    Select::make('gender')->label('性別')->options(Gender::options())->native(false),
                ]),
            Section::make('住所・連絡先')
                ->columns(2)
                ->schema([
                    TextInput::make('postal_code')->label('郵便番号')->placeholder('123-4567')->regex('/^\d{3}-?\d{4}$/')->maxLength(8),
                    TextInput::make('address')->label('住所')->maxLength(255)->columnSpanFull(),
                    TextInput::make('phone')->label('電話番号')->tel()->maxLength(32),
                    TextInput::make('mobile_phone')->label('携帯電話番号')->tel()->maxLength(32),
                    TextInput::make('contact_email')->label('連絡用メールアドレス')->email()->maxLength(255)->columnSpanFull(),
                    TextInput::make('emergency_contact_name')->label('緊急連絡先（氏名・続柄）')->maxLength(64),
                    TextInput::make('emergency_contact_phone')->label('緊急連絡先（電話番号）')->tel()->maxLength(32),
                ]),
            Section::make('パスワード変更')
                ->hidden(fn (): bool => $this->user()->managed_by_env)
                ->schema([
                    TextInput::make('password')
                        ->label('新しいパスワード')
                        ->password()
                        ->revealable()
                        ->rule(Password::min(8)->letters()->numbers())
                        ->autocomplete('new-password')
                        ->dehydrated(fn (#[SensitiveParameter] ?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state))
                        ->live(debounce: 500)
                        ->same('passwordConfirmation'),
                    TextInput::make('passwordConfirmation')
                        ->label('新しいパスワード（確認）')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required()
                        ->visible(fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false),
                    TextInput::make('currentPassword')
                        ->label('現在のパスワード')
                        ->password()
                        ->revealable()
                        ->autocomplete('current-password')
                        ->currentPassword(guard: 'web')
                        ->required()
                        ->visible(fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('logout')
                ->label('ログアウト')
                ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                ->color('gray')
                ->action(function (): void {
                    Auth::guard('web')->logout();
                    session()->invalidate();
                    session()->regenerateToken();

                    $this->redirect(route('login'));
                }),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $record->fill(array_intersect_key($data, array_flip(self::EDITABLE)));

        if (array_key_exists('password', $data) && ! $record->managed_by_env) {
            $record->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'must_change_password' => false,
            ]);
        }

        $record->save();

        return $record;
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'プロフィールを保存しました';
    }

    private function user(): User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : throw new \LogicException('The signed-in user must be an App\Models\User.');
    }
}
