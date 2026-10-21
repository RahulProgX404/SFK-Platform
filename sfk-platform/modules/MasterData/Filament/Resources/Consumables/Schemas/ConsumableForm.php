<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Modules\MasterData\Domain\Consumables\BoxType;
use Modules\MasterData\Domain\Consumables\ConsumableFields;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableLayout;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Partner;

/**
 * Create / edit form of 19. 消耗品マスタ, laid out like the legacy form (ConsumableLayout). 名称 is required and unique
 * (APP_19). 仕入先名 offers the legacy lookup choices: 仕入先 only, newest first (BR-MD-02). 在庫数 is shown, not typed:
 * it comes from the stock movements (入出庫 on the record page); 発注判定 follows it.
 */
final class ConsumableForm
{
    private const DECIMALS = ['reorder_point', 'lot_size'];

    private const PRICES = ['amount', 'unit_price'];

    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(ConsumableLayout::components(self::input(...), withFooter: false, withDeveloperFields: $superAdmin));
    }

    private static function input(string $name): Component
    {
        $label = ConsumableLayout::label($name);
        $supplier = fn (Get $get): ?Partner => is_numeric($get('supplier_id')) ? Partner::query()->find((int) $get('supplier_id')) : null;

        return match (true) {
            $name === 'name' => TextInput::make($name)->label($label)->required()->maxLength(64)->unique(ignoreRecord: true),
            $name === 'box_type' => Select::make($name)->label($label)->placeholder('-----')->options(BoxType::options())->native(false),
            $name === 'kind' => Select::make($name)->label($label)->placeholder('-----')->options(ConsumableKind::options())->native(false),
            in_array($name, self::DECIMALS, true) => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')
                ->minValue(0)->maxValue(9999999999)->live(onBlur: true),
            in_array($name, self::PRICES, true) => TextInput::make($name)->label($label)->numeric()->step('any')->rule('decimal:0,4')
                ->minValue(0)->maxValue(9999999999)->prefix('¥'),
            $name === 'unit' => TextInput::make($name)->label($label)->maxLength(64),
            $name === 'stock' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (?Consumable $record): ?string => ConsumableInfolist::quantity($record?->stock())),
            $name === 'supplier_id' => Select::make($name)
                ->label($label)
                ->relationship('supplier', 'name', fn (Builder $query): Builder => $query->where('is_supplier', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'name_kana'])
                ->preload()
                ->placeholder('-----')
                ->live(),
            $name === 'notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            $name === 'supplier_code' => TextEntry::make($name)->label($label)->placeholder('')->state(fn (Get $get): ?string => $supplier($get)?->code),
            $name === 'purchase_category' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get): ?string => $supplier($get)?->purchase_category?->value),
            $name === 'reorder_flag' => TextEntry::make($name)->label($label)->placeholder('')
                ->state(fn (Get $get, ?Consumable $record): string => ConsumableFields::reorderFlag($record?->stock(), self::scalar($get('reorder_point')))),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => throw new LogicException("No form input for [{$name}]."),
        };
    }

    private static function scalar(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
