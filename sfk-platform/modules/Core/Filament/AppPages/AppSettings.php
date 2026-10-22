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
 * `settings` page): アクセス権 (the app's permissions) and 高度な設定 → その他の設定 with インライン編集, コメント,
 * 「レコードの再利用」, 「変更履歴」 and 一括削除 (all on by default). Changes apply when saved.
 */
abstract class AppSettings extends Page
{
    use HasAppChrome;

    protected string $view = 'core::filament.app-settings';

    /** その他の設定: インライン編集 (pencil and × on the list rows) */
    public bool $inlineEditEnabled = true;

    /** その他の設定: コメント */
    public bool $commentsEnabled = true;

    /** その他の設定: レコードを再利用する */
    public bool $duplicateEnabled = true;

    /** その他の設定: 変更履歴を記録する */
    public bool $historyEnabled = true;

    /** その他の設定: 一括削除 */
    public bool $bulkDeleteEnabled = true;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator() && static::appModel() !== null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $app = $this->app();
        $this->inlineEditEnabled = $app->inline_edit_enabled;
        $this->commentsEnabled = $app->record_comments_enabled;
        $this->duplicateEnabled = $app->record_duplicate_enabled;
        $this->historyEnabled = $app->record_history_enabled;
        $this->bulkDeleteEnabled = $app->bulk_delete_enabled;
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

    /**
     * How much each アクセス権 holds: rows of the アプリ permissions, レコード settings, fields with フィールド settings.
     *
     * @return array{app: int, records: int, fields: int}
     */
    public function accessCounts(): array
    {
        $app = $this->app();

        return [
            'app' => $app->permissions()->count(),
            'records' => $app->recordRules()->count(),
            'fields' => $app->fieldPermissions()->distinct()->count('field'),
        ];
    }

    /** アクセス権 → レコード / フィールド (null where the app is not in this dashboard's settings). */
    public function accessUrl(string $page): ?string
    {
        return $this->appAccessUrl($this->app(), $page);
    }

    /** その他の設定 → 保存 */
    public function saveMisc(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->app()->forceFill([
            'inline_edit_enabled' => $this->inlineEditEnabled,
            'record_comments_enabled' => $this->commentsEnabled,
            'record_duplicate_enabled' => $this->duplicateEnabled,
            'record_history_enabled' => $this->historyEnabled,
            'bulk_delete_enabled' => $this->bulkDeleteEnabled,
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
