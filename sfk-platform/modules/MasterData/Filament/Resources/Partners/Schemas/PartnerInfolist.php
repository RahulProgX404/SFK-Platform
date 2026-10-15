<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Schemas;

use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Filament\Resources\Partners\PartnerLayout;
use Modules\MasterData\Models\Partner;

/** Record page of 01. 取引先マスタ, laid out like the legacy record (PartnerLayout), incl. 作成者 / 更新者. */
final class PartnerInfolist
{
    private const PHONES = ['parent_phone', 'parent_fax', 'phone', 'fax', 'supplier_phone', 'supplier_fax'];

    private const FLAGS = ['supplier_same_address', 'electronic_billing'];

    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(PartnerLayout::components(self::entry(...), withFooter: true, withDeveloperFields: $superAdmin));
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(PartnerLayout::label($name))->placeholder('—');

        return match (true) {
            $name === 'roles' => $entry
                ->state(fn (Partner $record): array => array_map(fn (PartnerRole $role): string => $role->label(), $record->roles()))
                ->badge()
                ->color('info'),
            $name === 'staff' => $entry->state(fn (Partner $record): array => $record->staff->pluck('name')->all())->badge()->color('gray'),
            $name === 'creator', $name === 'updater' => $entry->state(fn (Partner $record): ?string => $record->{$name}?->name),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            $name === 'collects_by_bill' => $entry->state(fn (Partner $record): ?string => $record->getAttribute('collects_by_bill') ? '手形' : null),
            in_array($name, self::FLAGS, true) => $entry->state(fn (Partner $record): ?string => $record->getAttribute($name) ? '✓' : null),
            in_array($name, self::PHONES, true) => $entry->url(fn (?string $state): ?string => filled($state) ? 'tel:'.preg_replace('/[^\d+]/', '', $state) : null),
            $name === 'contract_files', $name === 'supplier_contract_files' => $entry
                ->state(fn (Partner $record): array => array_map('basename', (array) $record->getAttribute($name)))
                ->listWithLineBreaks(),
            $name === 'bill_threshold_man' => $entry->suffix(' 万円'),
            $name === 'bill_site_days' => $entry->suffix(' 日'),
            default => $entry->formatStateUsing(fn (mixed $state): string => $state instanceof BackedEnum ? (string) $state->value : (string) $state),
        };
    }
}
