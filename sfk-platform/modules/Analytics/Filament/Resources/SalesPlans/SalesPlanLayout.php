<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Modules\Analytics\Domain\AnalyticsFields;
use Modules\Analytics\Domain\FiscalYear;
use Modules\Analytics\Models\PastSale;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\UserPopup;
use Modules\MasterData\Models\Partner;

/**
 * Form and record page of 25. 販売先別売上計画 (fields of SYSTEM_BRAIN APP_25 fields.md), on a 12-column grid of the 1,500 px
 * canvas: 得意先 (得意先ID lookup and 得意先名; 得意先コード, 旧得意先コード, グループ名 and 分類 from the master, 中分類), 計画
 * (日付 / 年, 計画値, 年間合計, 計画比進捗率), ▼ 計画値 and ▼ 実績 by month (May–April) and その他 (備考).
 */
final class SalesPlanLayout
{
    /** The customer's master values: attribute => partner column. */
    public const MASTER = ['customer_code' => 'code', 'legacy_customer_code' => 'legacy_customer_code', 'customer_name' => 'name', 'partner_group' => 'partner_group',
        'segment' => 'segment'];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 193]]];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::input(...), withFooter: false));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(self::entry(...), withFooter: true));
    }

    public static function label(string $attribute): string
    {
        if (preg_match('/^(plan|actual)_(m\d{2})$/', $attribute, $parts) === 1) {
            return AnalyticsFields::month($parts[2], padded: true);
        }

        return AnalyticsFields::SALES_PLAN[$attribute];
    }

    /** The month labels in lists and 絞り込む: 「05月(計画)」 / 「05月」 (実績, as in the legacy list). */
    public static function columnLabel(string $attribute): string
    {
        return str_starts_with($attribute, 'plan_m') ? self::label($attribute).'(計画)' : self::label($attribute);
    }

    private static function input(string $name): ?Component
    {
        $label = self::label($name);
        $customer = fn (Get $get): ?Partner => is_numeric($get('customer_id')) ? Partner::query()->find((int) $get('customer_id')) : null;
        $text = fn (callable $state): TextEntry => TextEntry::make($name)->label($label)->placeholder('')->state($state);
        $actualTotal = fn (Get $get): int => PastSale::sumOf(array_map(fn (string $column): mixed => $get($column), FiscalYear::columns('actual_')));

        return match (true) {
            preg_match('/^(plan|actual)_m\d{2}$/', $name) === 1 => TextInput::make($name)->label($label)->numeric()->integer()->minValue(-99999999999)->maxValue(99999999999)
                ->prefix('¥')->live(onBlur: true),
            $name === 'customer_id' => Select::make($name)
                ->label($label)
                ->relationship('customer', 'name', fn (Builder $query): Builder => $query->where('is_customer', true)->orderByDesc('id'))
                ->searchable(['name', 'short_name', 'code'])
                ->preload()
                ->placeholder('-----')
                ->live(),
            isset(self::MASTER[$name]) => $text(fn (Get $get): ?string => AppFormat::text($customer($get)?->getAttribute(self::MASTER[$name]))),
            $name === 'sub_category' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'planned_on' => DatePicker::make($name)->label($label)->live(),
            $name === 'year' => $text(fn (Get $get): ?string => filled($get('planned_on')) ? Carbon::parse((string) $get('planned_on'))->format('Y年m月') : null),
            $name === 'plan_total' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('¥')->live(onBlur: true),
            $name === 'actual_total' => $text(fn (Get $get): string => AppFormat::yen($actualTotal($get))),
            $name === 'progress' => $text(fn (Get $get): ?string => self::percent(FiscalYear::progress($actualTotal($get), is_numeric($get('plan_total')) ? (int) $get('plan_total') : null))),
            $name === 'notes' => TextInput::make($name)->label($label)->maxLength(255),
            $name === 'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            default => null,
        };
    }

    private static function entry(string $name): Component
    {
        $entry = TextEntry::make($name)->label(self::label($name))->placeholder('—');

        return match (true) {
            preg_match('/^(plan|actual)_m\d{2}$/', $name) === 1, $name === 'plan_total', $name === 'actual_total' => $entry
                ->formatStateUsing(fn (mixed $state): string => AppFormat::yen($state)),
            $name === 'customer_id' => $entry->state(fn (SalesPlan $record): ?string => $record->customer_id === null ? null : (string) $record->customer_id),
            isset(self::MASTER[$name]) => $entry->state(fn (SalesPlan $record): ?string => AppFormat::text($record->customer?->getAttribute(self::MASTER[$name]))),
            $name === 'planned_on' => $entry->date('Y-m-d'),
            $name === 'year' => $entry->state(fn (SalesPlan $record): ?string => $record->yearLabel()),
            $name === 'progress' => $entry->state(fn (SalesPlan $record): ?string => self::percent($record->progress())),
            $name === 'creator', $name === 'updater' => $entry->state(fn (SalesPlan $record): HtmlString => UserPopup::html(
                $record->getRelationValue($name) instanceof User ? $record->getRelationValue($name) : null,
            ))->html(),
            $name === 'created_at', $name === 'updated_at' => $entry->dateTime('Y-m-d H:i'),
            default => $entry,
        };
    }

    public static function percent(?string $progress): ?string
    {
        return $progress === null ? null : $progress.'%';
    }

    /**
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    private static function components(callable $make, bool $withFooter): array
    {
        $months = fn (string $prefix): array => array_map(fn (string $column): array => [$column, 250], FiscalYear::columns($prefix));
        $main = [
            '得意先',
            [['customer_id', 375], ['customer_name', 1125]],
            [['customer_code', 250], ['legacy_customer_code', 250], ['partner_group', 375], ['segment', 250], ['sub_category', 375]],
            '計画',
            [['planned_on', 250], ['year', 250], ['plan_total', 375], ['actual_total', 375], ['progress', 250]],
            self::label('plans'),
            array_slice($months('plan_'), 0, 6),
            array_slice($months('plan_'), 6),
            self::label('actuals'),
            array_slice($months('actual_'), 0, 6),
            array_slice($months('actual_'), 6),
            'その他',
            [['notes', 1500]],
        ];

        $components = [RecordSections::section($main, $make)];
        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }
}
