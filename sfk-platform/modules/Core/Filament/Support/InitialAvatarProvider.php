<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * The avatar of the user menu, drawn here as an inline SVG of the name's first character (like the ユーザー情報 popup):
 * Filament's default asks an outside service (ui-avatars.com) with every user's name, which leaks the names and needs
 * that service to be reachable.
 */
final class InitialAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $initial = htmlspecialchars(mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $name) ?: $name, 0, 1), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#09090b"/>'
            .'<text x="32" y="33" fill="#ffffff" font-family="Meiryo, sans-serif" font-size="32" text-anchor="middle" dominant-baseline="central">'
            .$initial.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
