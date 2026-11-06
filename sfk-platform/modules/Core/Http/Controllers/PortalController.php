<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Filament\Portal\PortalPage;
use Modules\Core\Http\Portal\DashboardDirectory;
use Modules\Core\Http\Portal\DashboardEntry;
use Modules\Core\Models\DashboardPortal;

/**
 * Entry point after sign-in: the welcome card, the dashboards the user may open and, below them, the お知らせ of those
 * dashboards (edited by administrators on the dashboard: ✎ opens its editor). A user with exactly one dashboard and
 * no お知らせ to read goes straight to the dashboard. Space / Enter opens the main dashboard (白石封筒).
 */
final class PortalController
{
    public function __invoke(Request $request, DashboardDirectory $dashboards): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        $entries = $dashboards->for($user);
        $notices = $this->notices($entries, $user);

        // one dashboard and nothing to read here: straight to it
        if (count($entries) === 1 && array_filter(array_column($notices, 'html')) === [] && ! $request->session()->has('status')) {
            return redirect()->to($entries[0]->url);
        }

        return view('core::portal', [
            'user' => $user,
            'dashboards' => $entries,
            'notices' => $notices,
            'unread' => $user->unreadNotifications()->count(),
            'keyboardUrl' => $this->keyboardUrl($entries),
        ]);
    }

    /**
     * Space / Enter on the portal opens the main dashboard (白石封筒, the default panel) — when the user may open it.
     *
     * @param  list<DashboardEntry>  $entries
     */
    private function keyboardUrl(array $entries): ?string
    {
        $main = Filament::getDefaultPanel()->getId();
        foreach ($entries as $entry) {
            if ($entry->id === $main) {
                return $entry->url;
            }
        }

        return null;
    }

    /**
     * お知らせ of the dashboards that have a ポータル (PortalPage): those with content, and for administrators every
     * one (to write it).
     *
     * @param  list<DashboardEntry>  $entries
     * @return list<array{dashboard: string, html: string|null, edit_url: string|null}>
     */
    private function notices(array $entries, User $user): array
    {
        $notices = [];
        foreach ($entries as $entry) {
            $panel = Filament::getPanels()[$entry->id] ?? null;
            if ($panel === null || ! in_array(PortalPage::class, $panel->getPages(), true)) {
                continue;
            }

            $notice = DashboardPortal::for($entry->id)->notice;
            $html = filled($notice) ? RichContentRenderer::make($notice)->toHtml() : null;
            $editUrl = $user->user_type->isAdministrator() ? $entry->url.'?'.http_build_query(['action' => 'editNotice', 'return' => 'portal']) : null;

            if ($html !== null || $editUrl !== null) {
                $notices[] = ['dashboard' => $entry->label, 'html' => $html, 'edit_url' => $editUrl];
            }
        }

        return $notices;
    }
}
