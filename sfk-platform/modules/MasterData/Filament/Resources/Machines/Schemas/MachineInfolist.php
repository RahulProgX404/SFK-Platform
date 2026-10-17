<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Money\UnitPrice;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Domain\Machines\MachineFields;
use Modules\MasterData\Filament\Resources\Machines\MachineLayout;
use Modules\MasterData\Models\Machine;

/** Record page of 03. 機械マスタ, laid out like the legacy record (MachineLayout), incl. 作成者 / 更新者. */
final class MachineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(MachineLayout::components(self::entry(...), withFooter: true, withDeveloperFields: $superAdmin));
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(MachineLayout::label($name))->placeholder('—');

        return match (true) {
            in_array($name, MachineFields::RATES, true) => $entry->formatStateUsing(fn (mixed $state): string => UnitPrice::format(is_scalar($state) ? (string) $state : null)),
            // user names open the ユーザー情報 popup
            $name === 'creator', $name === 'updater' => $entry->state(fn (Machine $record): HtmlString => UserPopup::html($record->{$name}))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }
}
