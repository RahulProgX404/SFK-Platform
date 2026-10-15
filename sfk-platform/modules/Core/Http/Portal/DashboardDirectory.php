<?php

declare(strict_types=1);

namespace Modules\Core\Http\Portal;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Modules\Core\Access\DashboardAccess;

/**
 * Dashboards (Filament panels) a user may open, as shown on the portal. Card texts come from the
 * owning module's config `<panel-id>.portal` (label, icon); the panel's brand name is
 * the fallback label.
 */
final class DashboardDirectory
{
    /** @return list<DashboardEntry> */
    public function for(User $user): array
    {
        $entries = [];

        foreach (Filament::getPanels() as $panel) {
            if (! $user->canAccessPanel($panel)) {
                continue;
            }

            $entries[] = $this->entry($panel);
        }

        return $entries;
    }

    private function entry(Panel $panel): DashboardEntry
    {
        $id = $panel->getId();
        $card = (array) config("{$id}.portal", []);

        return new DashboardEntry(
            id: $id,
            label: DashboardAccess::label($panel),
            icon: (string) ($card['icon'] ?? 'default'),
            url: (string) $panel->getUrl(),
        );
    }
}
