<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\AccidentReports\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Filament\QuickEntry\QuickEntry;
use Modules\Core\Filament\QuickEntry\QuickField;
use Modules\Core\Filament\QuickEntry\QuickPeople;
use Modules\Core\Filament\QuickEntry\QuickStep;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Application\AccidentReports\AccidentAlert;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportLayout;
use Modules\Production\Filament\Resources\AccidentReports\AccidentReportResource;
use Modules\Production\Filament\Resources\DailyReports\DailyReportLayout;
use Modules\Production\Models\AccidentDamage;
use Modules\Production\Models\AccidentReport;

/**
 * 新規 of 14. 事故報告管理 as a quick entry (user, 2026-11-09; SYSTEM_BRAIN APP_14 fields.md): 事故発生日 (今日 / 昨日), 報告者氏名
 * (the signed-in user to start with), 注番 (search; its 得意先名 / 品名 shown), 数量, 担当機械 (the job's machines first),
 * 製造者1 / 2, 営業関係者, the four texts of what happened, then 損害 (production::quick-entry.damages: one card per row —
 * 項目 紙 / 印刷, 枚数, 原価, 数量 — with its 小計 and the 合計損害額 as they are typed). 備考 is in 追加情報. Saved like the
 * record form: 損害 rows left empty are not kept, 合計損害額 follows the rows, and administrators and 営業関係者 are notified
 * (AccidentAlert).
 */
final class QuickAccidentReport extends QuickEntry
{
    /** The texts asked one by one. */
    public const TEXTS = ['description', 'cause', 'response', 'prevention'];

    /** The columns of a 損害 row. */
    public const DAMAGE_COLUMNS = ['item', 'sheets', 'cost', 'quantity'];

    public static function resource(): string
    {
        return AccidentReportResource::class;
    }

    public function heading(): string
    {
        return '事故報告を追加';
    }

    public function label(string $field): string
    {
        return AccidentReportLayout::label($field);
    }

    protected function stepList(): array
    {
        $today = now();
        $employees = fn (string $field): array => QuickPeople::employees(self::toId($this->data[$field] ?? null));
        $steps = [
            QuickStep::make('occurred', $this->label('occurred_on'), [QuickField::date('occurred_on', [
                $today->toDateString() => '今日',
                $today->copy()->subDay()->toDateString() => '昨日',
            ], required: true)]),
            QuickStep::make('reporter', $this->label('reporter_id'), [QuickField::person('reporter_id', $employees('reporter_id'), required: true)]),
            QuickStep::make('order', $this->label('order_id'), [QuickField::lookup('order_id', placeholder: '注番・品名で検索')]),
            QuickStep::make('quantity', $this->label('quantity'), [QuickField::number('quantity')]),
            QuickStep::make('machine', $this->label('machine_id'), [QuickField::choice('machine_id', DailyReportLayout::machineOptions(self::toId($this->data['order_id'] ?? null)), 2, required: true)]),
            QuickStep::make('makers', $this->label('maker1_id').'・'.$this->label('maker2_id'), [
                QuickField::select('maker1_id', $employees('maker1_id')),
                QuickField::select('maker2_id', $employees('maker2_id')),
            ]),
            QuickStep::make('sales', $this->label('sales_users'), [QuickField::people('sales_users', QuickPeople::users())]),
        ];
        foreach (self::TEXTS as $text) {
            // 事故内容 is required (AccidentReportRules): a report says what happened
            $steps[] = QuickStep::make($text, $this->label($text), [QuickField::textarea($text, required: $text === 'description')]);
        }
        $steps[] = QuickStep::make('damages', $this->label('damages'), [QuickField::custom('damages', 'production::quick-entry.damages')]);

        return $steps;
    }

    protected function detailList(): array
    {
        return [QuickField::textarea('notes')];
    }

    protected function defaults(): array
    {
        return [
            'occurred_on' => now()->toDateString(), 'reporter_id' => self::userId(), 'order_id' => null, 'quantity' => '', 'machine_id' => null,
            'maker1_id' => null, 'maker2_id' => null, 'sales_users' => [],
            'description' => '', 'cause' => '', 'response' => '', 'prevention' => '', 'damages' => [], 'notes' => '',
        ];
    }

    /** 損害: a new row (its 項目 still to choose). */
    public function addDamage(): void
    {
        $this->data['damages'] = [...$this->damageRows(), self::emptyDamage()];
    }

    public function removeDamage(int $index): void
    {
        $rows = $this->damageRows();
        unset($rows[$index]);
        $this->data['damages'] = array_values($rows);
        $this->resetErrorBag();
    }

    /** 項目 of a 損害 row tapped. */
    public function chooseDamageItem(int $index, string $item): void
    {
        if (isset($this->damageRows()[$index]) && in_array($item, ProductionChoices::DAMAGE_ITEMS, true)) {
            $this->data['damages'][$index]['item'] = $item;
        }
    }

    /**
     * 損害 rows as entered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function damageRows(): array
    {
        return array_values(array_filter((array) ($this->data['damages'] ?? []), 'is_array'));
    }

    /**
     * 小計 of a row: 原価 × 数量 (AccidentDamage, rounded half up).
     *
     * @param  array<string, mixed>  $row
     */
    public static function subtotal(array $row): int
    {
        return AccidentDamage::subtotalOf(self::decimal($row['cost'] ?? null), self::decimal($row['quantity'] ?? null));
    }

    /** 合計損害額 */
    public function totalDamage(): int
    {
        return array_sum(array_map(self::subtotal(...), $this->damageRows()));
    }

    /** 注番・品名 search: the newest jobs, or those whose 注番 / 品名 match (as far as the user may see 04. 受注入力). */
    public function lookupOptions(string $field, string $search): array
    {
        if ($field !== 'order_id') {
            return [];
        }

        $query = OrderResource::onlyVisible(Order::query())->select(['id', 'order_number', 'product_name'])->whereNotNull('order_number');
        $search = trim($search);
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                SearchQuery::where($query, 'order_number', $search);
                SearchQuery::where($query, 'product_name', $search, 'or');
            });
        }

        return $query->orderByDesc('id')->limit(8)->get()
            ->map(fn (Order $order): array => ['value' => (int) $order->id, 'label' => (string) $order->order_number, 'sub' => (string) $order->product_name])
            ->values()->all();
    }

    public function lookupSelected(string $field): ?array
    {
        $order = $field === 'order_id' && is_numeric($this->data['order_id'] ?? null)
            ? OrderResource::onlyVisible(Order::query())->with('customer')->find((int) $this->data['order_id'])
            : null;

        return $order instanceof Order ? [
            'label' => (string) $order->order_number,
            'sub' => (string) $order->product_name,
            'facts' => array_filter([$this->label('customer') => (string) $order->customer?->name]),
        ] : null;
    }

    /** The rules of every screen of the app (its RecordRules). */
    protected function rules(): array
    {
        return $this->recordRules();
    }

    protected function customSummary(string $field): string
    {
        $rows = array_filter($this->damageRows(), fn (array $row): bool => ! self::isBlankDamage($row));
        if ($field !== 'damages' || $rows === []) {
            return '';
        }

        return implode("\n", array_map(fn (array $row): string => trim(($row['item'] ?? '').'　'
            .self::number(self::decimal($row['sheets'] ?? null), '枚').'　'.AppFormat::yen(self::subtotal($row))), $rows));
    }

    protected function extraSummary(): array
    {
        return [['label' => $this->label('total_damage'), 'value' => AppFormat::yen($this->totalDamage()), 'screen' => '', 'invalid' => false]];
    }

    protected function create(array $values): Model
    {
        $report = AccidentReport::query()->create([
            'occurred_on' => $values['occurred_on'],
            'reporter_id' => self::toId($values['reporter_id']),
            'order_id' => self::toId($values['order_id']),
            'quantity' => self::decimal($values['quantity']),
            'machine_id' => self::toId($values['machine_id']),
            'maker1_id' => self::toId($values['maker1_id']),
            'maker2_id' => self::toId($values['maker2_id']),
            ...array_map(fn (mixed $text): mixed => self::blank(is_string($text) ? trim($text) : $text), array_intersect_key($values, array_flip([...self::TEXTS, 'notes']))),
        ]);

        $report->salesUsers()->sync(array_values(array_unique(array_filter(array_map(self::toId(...), (array) $values['sales_users'])))));

        $sort = 0;
        foreach ((array) $values['damages'] as $row) {
            if (is_array($row) && ! self::isBlankDamage($row)) {
                $report->damages()->create([
                    'sort' => ++$sort,
                    'item' => self::blank($row['item'] ?? null),
                    'sheets' => self::decimal($row['sheets'] ?? null),
                    'cost' => self::decimal($row['cost'] ?? null),
                    'quantity' => self::decimal($row['quantity'] ?? null),
                ]);
            }
        }

        return $report->refresh();
    }

    /** A new incident → administrators and 営業関係者 (AccidentAlert), once the 営業関係者 are saved. */
    protected function afterCreate(Model $record): void
    {
        $actor = auth()->user();
        if ($record instanceof AccidentReport) {
            app(AccidentAlert::class)->created($record, $actor instanceof User ? $actor : null, AccidentReportResource::titleOf($record));
        }
    }

    /** レコードを再利用する: everything but the legacy id and the derived total, with 営業関係者 and the 損害 rows. */
    protected function copyOf(Model $source): array
    {
        if (! $source instanceof AccidentReport) {
            return $this->defaults();
        }

        $plain = fn (mixed $value): string => is_numeric($value) ? (str_contains((string) $value, '.') ? rtrim(rtrim((string) $value, '0'), '.') : (string) $value) : '';

        return [
            'occurred_on' => $source->occurred_on->toDateString(),
            'reporter_id' => $source->reporter_id,
            'order_id' => $source->order_id,
            'quantity' => $plain($source->quantity),
            'machine_id' => $source->machine_id,
            'maker1_id' => $source->maker1_id,
            'maker2_id' => $source->maker2_id,
            'sales_users' => $source->salesUsers()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all(),
            ...array_map(fn (mixed $text): string => (string) $text, $source->only([...self::TEXTS, 'notes'])),
            'damages' => $source->damages->map(fn (AccidentDamage $row): array => [
                'item' => $row->item, 'sheets' => $plain($row->sheets), 'cost' => $plain($row->cost), 'quantity' => $plain($row->quantity),
            ])->all(),
        ];
    }

    /** @return array{item: null, sheets: string, cost: string, quantity: string} */
    private static function emptyDamage(): array
    {
        return ['item' => null, 'sheets' => '', 'cost' => '', 'quantity' => ''];
    }

    /** @param  array<string, mixed>  $row */
    private static function isBlankDamage(array $row): bool
    {
        foreach (self::DAMAGE_COLUMNS as $column) {
            $value = $row[$column] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** A typed number, or null. */
    private static function decimal(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return is_numeric($text) ? $text : null;
    }
}
