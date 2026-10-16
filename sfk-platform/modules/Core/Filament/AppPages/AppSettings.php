<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use LogicException;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\BusinessApp;

/**
 * アプリの設定 → 設定 of an app (legacy App settings tab, administrators only; register it as the resource's
 * `settings` page): アクセス権 (the app's permissions) and 高度な設定 → その他の設定 with 「レコードの再利用」 and
 * 「変更履歴」 (both on by default). Changes apply when saved.
 */
abstract class AppSettings extends Page
{
    use HasAppChrome;

    protected string $view = 'core::filament.app-settings';

    /** その他の設定: レコードを再利用する */
    public bool $duplicateEnabled = true;

    /** その他の設定: 変更履歴を記録する */
    public bool $historyEnabled = true;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator() && static::appModel() !== null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $app = $this->app();
        $this->duplicateEnabled = $app->record_duplicate_enabled;
        $this->historyEnabled = $app->record_history_enabled;
    }

    public function appPageKind(): string
    {
        return 'settings';
    }

    public function settingsTab(): string
    {
        return 'settings';
    }

    public function getTitle(): string
    {
        return 'アプリの設定';
    }

    public function permissionsUrl(): ?string
    {
        return $this->appPermissionsUrl($this->app());
    }

    /** その他の設定 → 保存 */
    public function saveMisc(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->app()->forceFill([
            'record_duplicate_enabled' => $this->duplicateEnabled,
            'record_history_enabled' => $this->historyEnabled,
        ])->save();

        Notification::make()->title('設定を保存しました')->success()->send();
    }

    protected function app(): BusinessApp
    {
        return static::appModel() ?? throw new LogicException('The app is not registered.');
    }

    protected static function appModel(): ?BusinessApp
    {
        $resource = static::getResource();

        return is_subclass_of($resource, AppResource::class) ? $resource::app() : null;
    }
}
