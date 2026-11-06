<?php

declare(strict_types=1);

namespace Modules\Production\Application\Schedules;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Filament\Support\AppFormat;
use Modules\MasterData\Models\Machine;
use Modules\Production\Domain\Schedules\ScheduleBoard;
use Modules\Production\Models\BagSchedule;

/**
 * The 製袋予定表 of 12. 製袋予定管理 (production-schedule.js): its schedules as board items — a day on a line with 担当者,
 * 注番, 品名, 部数, 備考 and a highlight — and the changes the board makes (add, edit, move to another day / line, copy,
 * highlight). The rules are those of the board: 注番 is 1–7 digits (sfk-0000123); with a 注番, 担当者 / 品名 / 部数 come
 * from 06. 売上入力 (SalesLookup), without one 品名 and 部数 must be typed; at most 10 備考 of 40 characters.
 *
 * Item: {id, date: Y-m-d, line: 機械コード, personInitial, orderNumber, productName, quantity, notes: list, highlightColor}.
 */
final class ProductionSchedule
{
    /** Most schedules sent to the board at once (a month and a week around it are a few hundred). */
    private const LIMIT = 5000;

    public function __construct(private readonly SalesLookup $sales) {}

    /**
     * The lines of the board whose machine is in 03. 機械マスタ, in order.
     *
     * @return list<array{key: string, label: string, subtitle?: string, color: string, soft: string, requiresManagerCheck?: bool, managerCheckMessage?: string, machine: int}>
     */
    public function lines(): array
    {
        $machines = Machine::query()->whereIn('code', ScheduleBoard::codes())->pluck('id', 'code');
        $lines = [];
        foreach (ScheduleBoard::LINES as $line) {
            $machine = $machines[$line['code']] ?? null;
            if ($machine === null) {
                continue;
            }

            $lines[] = array_filter([
                'key' => $line['code'],
                'label' => $line['label'],
                'subtitle' => $line['subtitle'] ?? null,
                'color' => $line['color'],
                'soft' => $line['soft'],
                'requiresManagerCheck' => isset($line['managerCheck']) ? true : null,
                'managerCheckMessage' => $line['managerCheck'] ?? null,
                'machine' => (int) $machine,
            ], fn (mixed $value): bool => $value !== null);
        }

        return $lines;
    }

    /**
     * The schedules of the days from–to (Y-m-d, both included) among those of the query.
     *
     * @param  Builder<BagSchedule>  $query
     * @return list<array<string, mixed>>
     */
    public function items(Builder $query, string $from, string $to): array
    {
        $from = self::day($from)->toDateString();
        $to = self::day($to)->toDateString();

        return $query->with(['machine', 'order'])
            ->whereDate('scheduled_on', '>=', $from)
            ->whereDate('scheduled_on', '<=', $to)
            ->orderBy('scheduled_on')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (BagSchedule $schedule): array => $this->item($schedule))
            ->values()
            ->all();
    }

    /** @return array{id: string, date: string, line: string, personInitial: string, orderNumber: string, productName: string, quantity: string, notes: list<string>, highlightColor: string|null} */
    public function item(BagSchedule $schedule): array
    {
        return [
            'id' => (string) $schedule->id,
            'date' => $schedule->scheduled_on?->toDateString() ?? '',
            'line' => (string) $schedule->machine?->code,
            'personInitial' => (string) $schedule->person_initial,
            'orderNumber' => (string) $schedule->orderNumber(),
            'productName' => (string) $schedule->productName(),
            'quantity' => $schedule->copies !== null ? AppFormat::number($schedule->copies) : '',
            'notes' => $schedule->noteList(),
            'highlightColor' => isset(ScheduleBoard::HIGHLIGHTS[(string) $schedule->highlight_color]) ? $schedule->highlight_color : null,
        ];
    }

    /**
     * 注番 typed on the board (sfk-0000123): 担当者 / 品名 / 部数 of 06. 売上入力, null when no sale has it.
     *
     * @return array{personInitial: string, productName: string, quantity: string}|null
     */
    public function lookup(string $orderNumber): ?array
    {
        if (! ScheduleBoard::isOrderNumber($orderNumber)) {
            throw new ScheduleRejected('注番は数字のみで入力してください。');
        }
        $sale = $this->sales->find($orderNumber);

        return $sale === null ? null : [
            'personInitial' => $sale['person'],
            'productName' => $sale['product'],
            'quantity' => $sale['quantity'] !== null ? AppFormat::number($sale['quantity']) : '',
        ];
    }

    /**
     * 予定を追加: a new schedule from the board's item (no highlight unless given).
     *
     * @param  array<string, mixed>  $item
     */
    public function create(array $item): BagSchedule
    {
        foreach (['date', 'line'] as $required) {
            $item[$required] ??= '';
        }
        $item += ['orderNumber' => '', 'personInitial' => '', 'productName' => '', 'quantity' => '', 'notes' => []];

        $schedule = new BagSchedule;
        $this->fill($schedule, $item);
        $schedule->save();

        return $schedule;
    }

    /**
     * 変更を保存, a move ({date, line}) or ハイライト ({highlightColor}): only the given values change.
     *
     * @param  array<string, mixed>  $patch
     */
    public function update(BagSchedule $schedule, array $patch): BagSchedule
    {
        $this->fill($schedule, $patch);
        $schedule->save();

        return $schedule;
    }

    /** コピー (drag and drop): the same schedule on another day / line, as it is (its highlight too). */
    public function copy(BagSchedule $source, string $date, string $line): BagSchedule
    {
        return DB::transaction(function () use ($source, $date, $line): BagSchedule {
            $copy = $source->replicate(['legacy_id', 'created_by', 'updated_by', 'created_at', 'updated_at']);
            $this->fill($copy, ['date' => $date, 'line' => $line]);
            $copy->save();

            return $copy;
        });
    }

    /** @param array<string, mixed> $values */
    private function fill(BagSchedule $schedule, array $values): void
    {
        if (array_key_exists('date', $values)) {
            $schedule->scheduled_on = Carbon::instance(self::day(self::text($values['date'])));
        }

        if (array_key_exists('line', $values)) {
            $line = self::text($values['line']);
            $machine = in_array($line, ScheduleBoard::codes(), true) ? Machine::query()->where('code', $line)->value('id') : null;
            if ($machine === null) {
                throw new ScheduleRejected('そのラインは存在しません。');
            }
            $schedule->machine_id = (int) $machine;
        }

        if (array_key_exists('highlightColor', $values)) {
            $color = self::text($values['highlightColor']);
            if ($color !== '' && ! isset(ScheduleBoard::HIGHLIGHTS[$color])) {
                throw new ScheduleRejected('無効な色です。');
            }
            $schedule->highlight_color = $color !== '' ? $color : null;
        }

        if (array_intersect(['orderNumber', 'personInitial', 'productName', 'quantity', 'notes'], array_keys($values)) !== []) {
            $this->fillContent($schedule, $values);
        }
    }

    /** @param array<string, mixed> $values */
    private function fillContent(BagSchedule $schedule, array $values): void
    {
        $orderNumber = self::text($values['orderNumber'] ?? '');
        $person = self::text($values['personInitial'] ?? '');
        $product = self::text($values['productName'] ?? '');
        $quantity = self::text($values['quantity'] ?? '');
        $notes = array_values(array_filter(array_map(self::text(...), is_array($values['notes'] ?? null) ? $values['notes'] : []), fn (string $note): bool => $note !== ''));

        if ($orderNumber !== '' && ! ScheduleBoard::isOrderNumber($orderNumber)) {
            throw new ScheduleRejected('注番は数字のみ、'.ScheduleBoard::ORDER_DIGITS.'桁以内で入力してください');
        }
        if (mb_strlen($person) > 40) {
            throw new ScheduleRejected('担当者は40文字以内で入力してください');
        }
        if (mb_strlen($product) > 120) {
            throw new ScheduleRejected('品名は120文字以内で入力してください');
        }
        if (mb_strlen($quantity) > 40) {
            throw new ScheduleRejected('部数は40文字以内で入力してください');
        }
        foreach ($notes as $note) {
            if (mb_strlen($note) > 40) {
                throw new ScheduleRejected('備考は1件40文字以内で入力してください');
            }
        }
        if (count($notes) > ScheduleBoard::MAX_NOTES) {
            throw new ScheduleRejected('備考は'.ScheduleBoard::MAX_NOTES.'件までです');
        }

        if ($orderNumber !== '') {
            $sale = $this->sales->find($orderNumber);
            if ($sale === null) {
                throw new ScheduleRejected("注番「{$orderNumber}」に一致するレコードが06. 売上入力に見つかりません。");
            }
            $schedule->order_id = $sale['order']->id;
            $schedule->person_initial = $sale['person'] !== '' ? $sale['person'] : null;
            $schedule->product_name = $sale['product'] !== '' ? $sale['product'] : null;
            $schedule->copies = $sale['quantity'];
        } else {
            if ($product === '') {
                throw new ScheduleRejected('注番が未入力の場合は品名を入力してください');
            }
            if ($quantity === '') {
                throw new ScheduleRejected('注番が未入力の場合は部数を入力してください');
            }
            $schedule->order_id = null;
            $schedule->free_order_number = null;
            $schedule->free_product_name = null;
            $schedule->person_initial = $person !== '' ? $person : null;
            $schedule->product_name = $product;
            $schedule->copies = self::quantity($quantity);
        }

        $schedule->notes = $notes !== [] ? implode("\n", $notes) : null;
    }

    /** 部数 as typed ("1,000") → a number. */
    private static function quantity(string $value): string
    {
        $number = str_replace([',', '，', ' ', '　'], '', mb_convert_kana($value, 'n'));
        if (preg_match('/^\d{1,12}(\.\d{1,4})?$/', $number) !== 1) {
            throw new ScheduleRejected('部数は数字で入力してください');
        }

        return $number;
    }

    private static function day(string $value): CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
        if (! $day instanceof CarbonImmutable || $day->toDateString() !== $value) {
            throw new ScheduleRejected('日付の形式が正しくありません。');
        }

        return $day;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
