<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables\Schemas;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Modules\Core\Domain\Money\UnitPrice;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Domain\Consumables\StockMovementReason;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableLayout;
use Modules\MasterData\Filament\Resources\Consumables\ConsumableResource;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\ConsumableStockMovement;

/**
 * Record page of 19. 消耗品マスタ, laid out like the legacy record (ConsumableLayout), incl. 作成者 / 更新者, and below it
 * 入出庫履歴: the stock movements behind 在庫数, newest first, with 入出庫を登録 (users who may edit the record).
 */
final class ConsumableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components([
                ...ConsumableLayout::components(self::entry(...), withFooter: true, withDeveloperFields: $superAdmin),
                Section::make('入出庫履歴')
                    ->key('stockMovements')
                    ->headerActions([self::recordMovementAction()])
                    ->schema([View::make('core::filament.related-table')->viewData(fn (Consumable $record): array => self::movements($record))])
                    ->hidden(fn (mixed $livewire): bool => (bool) ($livewire->printMode ?? false) || ($livewire->historyVersion ?? null) !== null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * 入出庫を登録 (users who may edit the record): 入庫 adds, 棚卸調整 adds or takes out the counted difference. 使用 comes
     * from the daily reports only.
     */
    public static function recordMovementAction(): Action
    {
        return Action::make('recordStockMovement')
            ->label('入出庫を登録')
            ->icon(Heroicon::OutlinedPlus)
            ->size('sm')
            ->visible(fn (Consumable $record): bool => ConsumableResource::canEdit($record))
            ->modalHeading('入出庫を登録')
            ->modalWidth('md')
            ->modalSubmitActionLabel('登録する')
            ->modalCancelActionLabel('キャンセル')
            ->schema([
                DatePicker::make('moved_on')->label('日付')->required()->default(now())->native(false)->displayFormat('Y-m-d'),
                Radio::make('reason')
                    ->label('区分')
                    ->options([StockMovementReason::Receipt->value => StockMovementReason::Receipt->label(), StockMovementReason::Adjustment->value => StockMovementReason::Adjustment->label()])
                    ->default(StockMovementReason::Receipt->value)
                    ->required()
                    ->inline(),
                TextInput::make('quantity')
                    ->label('数量')
                    ->required()
                    ->numeric()
                    ->step('any')
                    ->rule('decimal:0,4')
                    ->rule('not_in:0')
                    ->minValue(fn (Get $get): ?int => $get('reason') === StockMovementReason::Receipt->value ? 0 : null)
                    ->maxValue(9999999999),
                TextInput::make('note')->label('備考')->maxLength(255),
            ])
            ->action(function (array $data, Consumable $record): void {
                $record->stockMovements()->create([
                    'moved_on' => $data['moved_on'],
                    'reason' => $data['reason'],
                    'quantity' => $data['quantity'],
                    'note' => filled($data['note'] ?? null) ? $data['note'] : null,
                ]);

                Notification::make()->success()->title('入出庫を登録しました')->send();
            });
    }

    /**
     * @return array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<null>}
     */
    private static function movements(Consumable $record): array
    {
        $rows = $record->stockMovements()->with('creator')->orderByDesc('moved_on')->orderByDesc('id')->get()
            ->map(fn (ConsumableStockMovement $movement): array => [
                'moved_on' => $movement->moved_on->format('Y-m-d'),
                'reason' => $movement->reason->label(),
                'quantity' => (string) self::quantity($movement->quantity),
                'note' => (string) $movement->note,
                'creator' => (string) $movement->creator?->name,
            ])
            ->all();

        return [
            'columns' => ['moved_on' => '日付', 'reason' => '区分', 'quantity' => '数量', 'note' => '備考', 'creator' => '登録者'],
            'numeric' => ['quantity'],
            'rows' => $rows,
            'urls' => [],
        ];
    }

    /** 在庫数 / 発注点 / ロット数: up to 4 decimals, no trailing zeros, thousands separators. */
    public static function quantity(int|float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(ConsumableLayout::label($name))->placeholder('—');

        return match ($name) {
            'amount', 'unit_price' => $entry->formatStateUsing(fn (mixed $state): string => UnitPrice::format(is_scalar($state) ? (string) $state : null)),
            'reorder_point', 'lot_size' => $entry->formatStateUsing(fn (mixed $state): ?string => self::quantity(is_scalar($state) ? (string) $state : null)),
            'stock' => $entry->state(fn (Consumable $record): ?string => self::quantity($record->stock())),
            'box_type', 'kind' => $entry->formatStateUsing(fn (mixed $state): string => $state instanceof BackedEnum ? (string) $state->value : ''),
            'supplier_id' => $entry->state(fn (Consumable $record): ?string => $record->supplier?->name),
            'supplier_code' => $entry->state(fn (Consumable $record): ?string => $record->supplier?->code),
            'purchase_category' => $entry->state(fn (Consumable $record): ?string => $record->supplier?->purchase_category?->value),
            'reorder_flag' => $entry->state(fn (Consumable $record): string => $record->reorderFlag()),
            // user names open the ユーザー情報 popup
            'creator', 'updater' => $entry->state(fn (Consumable $record): HtmlString => UserPopup::html($record->{$name}))->html(),
            'created_at', 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }
}
