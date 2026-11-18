<?php

declare(strict_types=1);

namespace Modules\Messaging\Filament;

use App\Modules\Filament\ModulePlugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Modules\Messaging\Filament\Pages\MessengerPage;
use Modules\Messaging\Http\MessengerAssets;

/**
 * Adds the messenger (Pages/MessengerPage) to the "erp" panel. The messenger page loads normally (not swapped in place
 * by SPA navigation): its own script must start before Alpine, and it is the one page that keeps a pulse running.
 */
final class MessagingPlugin extends ModulePlugin
{
    public function getId(): string
    {
        return 'messaging';
    }

    protected function registerExtra(Panel $panel): void
    {
        $panel->spaUrlExceptions([...$panel->getSpaUrlExceptions(), '*/messages', '*/messages?*']);
        // its stylesheet and script, on that page only
        $panel->renderHook(PanelsRenderHook::HEAD_END, fn (): string => MessengerAssets::headTags(), scopes: MessengerPage::class);
        // メッセージ in the top bar of the dashboard's home, right after アプリ (registered before the plugins)
        $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): string => MessengerPage::canAccess()
            ? view('messaging::header-button', ['url' => MessengerPage::getUrl()])->render()
            : '');
    }
}
