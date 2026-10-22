<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Domain\Apps\AppStatus;
use Modules\Core\Models\BusinessApp;
use Modules\Settings\Filament\NavigationGroup;
use UnitEnum;

/**
 * アプリ設定 (permission.md): every app on a board with one column per status. Apps come from the code (added
 * when their screens are built, removed when deleted — AppLifecycle::syncWithCode on every visit), so there
 * is no create or delete here. Super admins drag along the whole pipeline and set the icon text; admins may
 * only drag 準備完了 → 公開中 and 公開中 ⇄ 停止中. The rules live in AppStatus / AppLifecycle.
 */
final class AppsSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Apps->value;

    protected static ?string $navigationLabel = 'アプリ設定';

    protected static ?string $title = 'アプリ設定';

    protected static ?string $slug = 'apps';

    protected static ?int $navigationSort = 1;

    protected string $view = 'settings::pages.apps-settings';

    public static function canAccess(): bool
    {
        return self::actor()?->user_type->isAdministrator() ?? false;
    }

    /** The board always reflects the code: apps built since the last visit appear, deleted ones go. */
    public function mount(): void
    {
        app(AppLifecycle::class)->syncWithCode(app(AppDiscovery::class)->definitions(), app(AppDiscovery::class)->settingDefaults());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $superAdmin = self::actor()?->isSuperAdmin() ?? false;
        $apps = BusinessApp::query()->with('spaces')->orderBy('sort_order')->get()->groupBy(fn (BusinessApp $app): string => $app->status->value);

        return [
            'columns' => array_map(fn (AppStatus $status): array => [
                'status' => $status,
                'apps' => $apps->get($status->value, collect()),
            ], AppStatus::cases()),
            'transitions' => collect(AppStatus::cases())->mapWithKeys(fn (AppStatus $status): array => [
                $status->value => array_map(fn (AppStatus $to): string => $to->value, $status->targets($superAdmin)),
            ])->all(),
            'superAdmin' => $superAdmin,
        ];
    }

    /** Called when an app card is dropped on another column (or from the card's 移動 menu). */
    public function moveApp(int $appId, string $status): void
    {
        $app = BusinessApp::query()->findOrFail($appId);
        $to = AppStatus::from($status);

        try {
            app(AppLifecycle::class)->move($app, $to, self::actorOrFail());
        } catch (AppActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("「{$app->name}」を「{$to->label()}」に移動しました")->send();
    }

    public function editAppAction(): Action
    {
        return Action::make('editApp')
            ->label('編集')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => self::actor()?->isSuperAdmin() ?? false)
            ->modalHeading('アプリを編集')
            ->fillForm(fn (array $arguments): array => BusinessApp::query()->findOrFail($arguments['app'])->only(['icon_text']))
            ->schema([
                TextInput::make('icon_text')
                    ->label('アイコン文字')
                    ->placeholder('例：受注')
                    ->maxLength(4),
            ])
            ->action(function (array $data, array $arguments): void {
                app(AppLifecycle::class)->updateIconText(
                    BusinessApp::query()->findOrFail($arguments['app']),
                    isset($data['icon_text']) ? (string) $data['icon_text'] : null,
                    self::actorOrFail(),
                );

                Notification::make()->success()->title('アプリを保存しました')->send();
            });
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function actorOrFail(): User
    {
        return self::actor() ?? throw new AppActionNotAllowed('ログインが必要です。');
    }
}
