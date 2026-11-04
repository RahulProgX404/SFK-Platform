<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Profile;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Url;
use Modules\Core\Application\Notifications\NotificationPreferences;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Filament\AppResource;
use Modules\Core\Http\Portal\DashboardDirectory;
use Modules\Core\Models\BusinessApp;
use SensitiveParameter;

/**
 * プロフィール (user menu of every dashboard; design_ref/profile-page.png): a menu of settings on the left, the chosen
 * one on the right with キャンセル / 変更を保存 under it.
 * - 一般設定 (everyone, their own account): 氏名, フリガナ, 性別 and 住所・連絡先.
 * - 通知設定, 従業員マスタ情報, パスワード設定 and 利用可能なダッシュボード: administrators only (ユーザー種別). 従業員マスタ情報
 *   and the dashboards are shown, not changed here (ユーザー管理 maintains them).
 * What a user may not open is neither shown nor saved (the fields of the other settings are not sent).
 */
final class ProfilePage extends EditProfile
{
    /** Attributes the user may change themselves (一般設定). */
    private const EDITABLE = [
        'last_name', 'first_name', 'last_name_kana', 'first_name_kana', 'gender',
        'postal_code', 'address', 'phone', 'mobile_phone', 'contact_email',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    /** The settings of the menu: key => [label, icon, administrators only, can be saved]. */
    public const SECTIONS = [
        'general' => ['一般設定', 'heroicon-o-cog-6-tooth', false, true],
        'notifications' => ['通知設定', 'heroicon-o-bell', true, true],
        'employee' => ['従業員マスタ情報', 'heroicon-o-identification', true, false],
        'password' => ['パスワード設定', 'heroicon-o-lock-closed', true, true],
        'dashboards' => ['利用可能なダッシュボード', 'heroicon-o-squares-2x2', true, false],
    ];

    protected string $view = 'core::filament.profile';

    #[Url(as: 'tab')]
    public string $section = 'general';

    public static function getLabel(): string
    {
        return 'プロフィール';
    }

    public function getTitle(): string
    {
        return 'プロフィール';
    }

    public function mount(): void
    {
        parent::mount();

        if (! array_key_exists($this->section, $this->sections())) {
            $this->section = 'general';
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: bool, 3: bool}> the settings the user may open */
    public function sections(): array
    {
        $isAdministrator = $this->user()->user_type->isAdministrator();

        return array_filter(self::SECTIONS, fn (array $section): bool => ! $section[2] || $isAdministrator);
    }

    public function showSection(string $section): void
    {
        if (array_key_exists($section, $this->sections())) {
            $this->section = $section;
            $this->resetErrorBag();
        }
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        $on = fn (string $section): bool => $this->section === $section && array_key_exists($section, $this->sections());

        return $schema->components([
            Section::make('基本情報')
                ->icon(Heroicon::OutlinedUser)
                ->visible(fn (): bool => $on('general'))
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('表示名'),
                    TextEntry::make('email')->label('メールアドレス（ログインID）'),
                    TextEntry::make('user_type')
                        ->label('ユーザー種別')
                        ->state(fn (): string => $this->user()->user_type->label()),
                    TextInput::make('last_name')->label('姓')->maxLength(64),
                    TextInput::make('first_name')->label('名')->maxLength(64),
                    Select::make('gender')->label('性別')->options(Gender::options())->native(false),
                    TextInput::make('last_name_kana')->label('姓（フリガナ）')->maxLength(64),
                    TextInput::make('first_name_kana')->label('名（フリガナ）')->maxLength(64),
                ]),
            Section::make('住所・連絡先')
                ->icon(Heroicon::OutlinedMapPin)
                ->visible(fn (): bool => $on('general'))
                ->columns(3)
                ->schema([
                    TextInput::make('postal_code')->label('郵便番号')->placeholder('123-4567')->regex('/^\d{3}-?\d{4}$/')->maxLength(8),
                    TextInput::make('address')->label('住所')->maxLength(255)->columnSpan(2),
                    TextInput::make('phone')->label('電話番号')->tel()->maxLength(32),
                    TextInput::make('mobile_phone')->label('携帯電話番号')->tel()->maxLength(32),
                    TextInput::make('contact_email')->label('連絡用メールアドレス')->email()->maxLength(255),
                    TextInput::make('emergency_contact_name')->label('緊急連絡先（氏名・続柄）')->maxLength(64),
                    TextInput::make('emergency_contact_phone')->label('緊急連絡先（電話番号）')->tel()->maxLength(32),
                ]),
            Section::make('通知設定')
                ->icon(Heroicon::OutlinedBell)
                ->visible(fn (): bool => $on('notifications'))
                ->columns(2)
                ->schema([
                    CheckboxList::make('notifications_receive')
                        ->label('受け取る')
                        ->options(fn (): array => $this->notificationOptions())
                        ->live(),
                    CheckboxList::make('notifications_mail')
                        ->label('メールでも受け取る')
                        ->options(fn (): array => $this->notificationOptions())
                        ->disableOptionWhen(fn (string $value, Get $get): bool => ! in_array($value, (array) $get('notifications_receive'), true)),
                ]),
            Section::make('従業員マスタ情報')
                ->icon(Heroicon::OutlinedIdentification)
                ->visible(fn (): bool => $on('employee'))
                ->columns(3)
                ->schema([
                    TextEntry::make('employee_code')->label('社員番号')->placeholder('—'),
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
                    TextEntry::make('sales_staff_code')->label('営業担当者コード')->placeholder('—'),
                    TextEntry::make('worker_code')->label('作業者コード')->placeholder('—'),
                    TextEntry::make('department_code')->label('部署コード')->placeholder('—'),
                ]),
            Section::make('パスワード設定')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => $on('password'))
                ->columns(3)
                ->schema(fn (): array => $this->user()->managed_by_env
                    // the super administrator of the environment keeps the password of SUPER_ADMIN_PASSWORD
                    ? [TextEntry::make('password_note')->hiddenLabel()->state('このアカウントのパスワードはサーバーの設定で管理されています。')->columnSpanFull()]
                    : [
                        TextInput::make('currentPassword')
                            ->label('現在のパスワード')
                            ->password()
                            ->revealable()
                            ->autocomplete('current-password')
                            ->currentPassword(guard: 'web')
                            ->requiredWith('password')
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label('新しいパスワード')
                            ->password()
                            ->revealable()
                            ->rule(Password::min(8)->letters()->numbers())
                            ->autocomplete('new-password')
                            ->dehydrated(fn (#[SensitiveParameter] ?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state))
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label('新しいパスワード（確認）')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->requiredWith('password')
                            ->dehydrated(false),
                    ]),
            Section::make('利用可能なダッシュボード')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->visible(fn (): bool => $on('dashboards'))
                ->schema([
                    TextEntry::make('dashboards_available')
                        ->hiddenLabel()
                        ->state(fn (): array => array_map(fn ($entry): string => $entry->label, app(DashboardDirectory::class)->for($this->user())))
                        ->badge()
                        ->color('primary')
                        ->placeholder('利用できるダッシュボードはありません。'),
                ]),
        ]);
    }

    /** キャンセル on the left, 変更を保存 on the right; none for the settings that are only shown. */
    protected function getFormActions(): array
    {
        if (! (self::SECTIONS[$this->section][3] ?? false) || ($this->section === 'password' && $this->user()->managed_by_env)) {
            return [];
        }

        return [
            $this->getCancelFormAction()->label('キャンセル'),
            $this->getSaveFormAction()->label('変更を保存'),
        ];
    }

    public function getFormActionsAlignment(): Alignment
    {
        return Alignment::Between;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $preferences = app(NotificationPreferences::class);

        return [
            ...parent::mutateFormDataBeforeFill($data),
            'notifications_receive' => $preferences->receiving($this->user()),
            'notifications_mail' => $preferences->mailing($this->user()),
        ];
    }

    /**
     * Saves only the setting that is open, and only what the user may change there.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $allowed = $this->sections();

        if ($this->section === 'general' && isset($allowed['general'])) {
            $record->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
        }

        if ($this->section === 'notifications' && isset($allowed['notifications'])) {
            app(NotificationPreferences::class)->save(
                $record,
                array_values(array_map('strval', (array) ($data['notifications_receive'] ?? []))),
                array_values(array_map('strval', (array) ($data['notifications_mail'] ?? []))),
            );
        }

        if ($this->section === 'password' && isset($allowed['password']) && array_key_exists('password', $data) && ! $record->managed_by_env) {
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

    /** @return array<string, string> kind => 「<app name>: <label>」 */
    private function notificationOptions(): array
    {
        $options = [];
        foreach (app(NotificationPreferences::class)->kindsFor($this->user()) as $kind) {
            $resource = $kind->appCode !== null ? AppResource::forCode($kind->appCode) : null;
            $app = $kind->appCode !== null ? (BusinessApp::forCode($kind->appCode)->name ?? ($resource !== null ? $resource::appName() : null)) : null;
            $options[$kind->key] = $app !== null ? $app.': '.$kind->label : $kind->label;
        }

        return $options;
    }

    private function user(): User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : throw new \LogicException('The signed-in user must be an App\Models\User.');
    }
}
