<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * The legacy 「フィールドに吹き出しを設定する」 of an app's form: a ? icon after the field label showing the message on hover
 * (nothing is printed on the form itself).
 */
final class FieldTip
{
    /**
     * @template TComponent of Component
     *
     * @param  TComponent  $component
     * @return TComponent
     */
    public static function add(Component $component, string $message): Component
    {
        if ($component instanceof Field || $component instanceof Entry) {
            $component->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: $message)->hintColor('gray');
        }

        return $component;
    }
}
