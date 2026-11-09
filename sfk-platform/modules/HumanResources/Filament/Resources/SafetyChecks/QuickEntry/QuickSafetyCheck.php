<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\QuickEntry\QuickEntry;
use Modules\Core\Filament\QuickEntry\QuickField;
use Modules\Core\Filament\QuickEntry\QuickPeople;
use Modules\Core\Filament\QuickEntry\QuickStep;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\SafetyChecks\ChecklistTemplate;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckLayout;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Models\SafetyCheck;
use Modules\HumanResources\Models\SafetyCheckItem;

/**
 * 新規 of 17. 安全衛生チェック as a quick entry (user, 2026-11-09; SYSTEM_BRAIN APP_17 fields.md): 部署 (a tap; it brings the
 * items of the department, BR-HR-05), 部署・機械, then the チェックリスト as one card per item (hr::quick-entry.checklist):
 * 評価 はい / いいえ with one tap — which also gives the row its チェック, required on every row as in the legacy form —
 * a ✓ to check a row without changing its 評価, 備考 per item, 「すべてチェック」, and the switch of the driving items
 * 20–25 (「※運転をしてる方は20～25を記入してください」). 日付 is today and 名前 the signed-in user unless changed in 追加情報.
 */
final class QuickSafetyCheck extends QuickEntry
{
    public static function resource(): string
    {
        return SafetyCheckResource::class;
    }

    public function heading(): string
    {
        return '安全衛生チェックを追加';
    }

    public function label(string $field): string
    {
        return SafetyCheckLayout::label($field);
    }

    protected function stepList(): array
    {
        return [
            QuickStep::make('department', $this->label('department'), [QuickField::choice('department', HrChoices::options(HrChoices::DEPARTMENTS), 3, required: true)]),
            QuickStep::make('machine', $this->label('department_machine'), [QuickField::text('department_machine')]),
            QuickStep::make('checklist', $this->label('items'), [QuickField::custom('items', 'hr::quick-entry.checklist', required: true)]),
        ];
    }

    protected function detailList(): array
    {
        $today = now();

        return [
            QuickField::date('checked_on', [$today->toDateString() => '今日', $today->copy()->subDay()->toDateString() => '昨日'], required: true)->inReview(),
            QuickField::select('user_id', QuickPeople::users(self::toId($this->data['user_id'] ?? null)), required: true)->inReview(),
            QuickField::select('employee_id', QuickPeople::employees(self::toId($this->data['employee_id'] ?? null))),
        ];
    }

    protected function defaults(): array
    {
        return [
            'department' => null, 'department_machine' => '', 'items' => self::rows(ChecklistTemplate::numbersFor(null)), 'driving' => false,
            'checked_on' => now()->toDateString(), 'user_id' => self::userId(), 'employee_id' => null,
        ];
    }

    /** 部署 chosen: the items of the department while none is checked yet (BR-HR-05). */
    protected function chosen(string $field): void
    {
        if ($field !== 'department' || $this->checkedCount() > 0) {
            return;
        }

        $department = is_string($this->data['department'] ?? null) ? $this->data['department'] : null;
        $this->data['items'] = self::rows(ChecklistTemplate::numbersFor($department));
        $this->data['driving'] = $department === ChecklistTemplate::SALES_DEPARTMENT;
    }

    /** The switch 「運転をする」: the driving items 20–25 added (in their place) or taken out. */
    public function setDriving(bool $on): void
    {
        $rows = array_values(array_filter((array) ($this->data['items'] ?? []), fn (mixed $row): bool => is_array($row)
            && ! in_array(self::numberOf($row), ChecklistTemplate::DRIVING, true)));
        if ($on) {
            $rows = [...$rows, ...self::rows(ChecklistTemplate::DRIVING)];
            usort($rows, fn (array $a, array $b): int => (self::numberOf($a) ?? PHP_INT_MAX) <=> (self::numberOf($b) ?? PHP_INT_MAX));
        }

        $this->data['items'] = $rows;
        $this->data['driving'] = $on;
        $this->resetErrorBag('data.items');
    }

    /** 「すべてチェック」 */
    public function checkAll(): void
    {
        $this->data['items'] = array_map(fn (mixed $row): mixed => is_array($row) ? [...$row, 'checked' => true] : $row, (array) ($this->data['items'] ?? []));
        $this->resetErrorBag();
    }

    public function checkedCount(): int
    {
        return count(array_filter((array) ($this->data['items'] ?? []), fn (mixed $row): bool => is_array($row) && ($row['checked'] ?? false) === true));
    }

    /** The rules of every screen of the app (its RecordRules). */
    protected function rules(): array
    {
        return $this->recordRules();
    }

    protected function customSummary(string $field): string
    {
        $rows = array_filter((array) ($this->data['items'] ?? []), 'is_array');
        $no = count(array_filter($rows, fn (array $row): bool => ($row['answer'] ?? null) === HrChoices::ANSWERS[1]));

        return count($rows).'項目（'.HrChoices::CHECK.' '.$this->checkedCount().' / '.count($rows).'）'.($no > 0 ? '　'.HrChoices::ANSWERS[1].' '.$no.'件' : '');
    }

    protected function create(array $values): Model
    {
        $check = SafetyCheck::query()->create([
            'checked_on' => $values['checked_on'],
            'user_id' => self::toId($values['user_id']),
            'employee_id' => self::toId($values['employee_id']),
            'department' => $values['department'],
            'department_machine' => self::blank(trim((string) $values['department_machine'])),
        ]);

        $sort = 0;
        foreach ((array) $values['items'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $check->items()->create([
                'sort' => ++$sort,
                'number' => self::numberOf($row),
                'item' => self::blank(trim((string) ($row['item'] ?? ''))),
                'answer' => in_array($row['answer'] ?? null, HrChoices::ANSWERS, true) ? $row['answer'] : HrChoices::ANSWERS[0],
                'checked' => ($row['checked'] ?? false) === true,
                'notes' => self::blank(trim((string) ($row['notes'] ?? ''))),
            ]);
        }

        return $check;
    }

    /** レコードを再利用する: this month's check from the same items — 評価 back to はい, チェック and 備考 empty. */
    protected function copyOf(Model $source): array
    {
        if (! $source instanceof SafetyCheck) {
            return $this->defaults();
        }

        $rows = $source->items->map(fn (SafetyCheckItem $row): array => [
            'number' => self::numberOf(['number' => $row->number]), 'item' => (string) $row->item, 'answer' => HrChoices::ANSWERS[0], 'checked' => false, 'notes' => '',
        ])->all();

        return [
            ...$this->defaults(),
            'department' => $source->department,
            'department_machine' => (string) $source->department_machine,
            'items' => $rows !== [] ? $rows : self::rows(ChecklistTemplate::numbersFor($source->department)),
            'driving' => array_intersect(array_map(fn (array $row): ?int => self::numberOf($row), $rows), ChecklistTemplate::DRIVING) !== [],
            'user_id' => $source->user_id ?? self::userId(),
            'employee_id' => $source->employee_id,
        ];
    }

    /**
     * Unanswered rows of these items (ChecklistTemplate).
     *
     * @param  list<int>  $numbers
     * @return list<array{number: int, item: string, answer: string, checked: bool, notes: string}>
     */
    private static function rows(array $numbers): array
    {
        return array_map(fn (int $number): array => [
            'number' => $number, 'item' => (string) ChecklistTemplate::item($number), 'answer' => HrChoices::ANSWERS[0], 'checked' => false, 'notes' => '',
        ], $numbers);
    }

    /** @param  array<string, mixed>  $row */
    private static function numberOf(array $row): ?int
    {
        return is_numeric($row['number'] ?? null) ? (int) $row['number'] : null;
    }
}
