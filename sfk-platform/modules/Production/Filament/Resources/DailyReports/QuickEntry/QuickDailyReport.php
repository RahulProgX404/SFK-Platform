<?php

declare(strict_types=1);

namespace Modules\Production\Filament\Resources\DailyReports\QuickEntry;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Support\VisibleChoice;
use Modules\MasterData\Domain\Consumables\ConsumableKind;
use Modules\MasterData\Models\Consumable;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Domain\Orders\OrderChoices;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Models\Order;
use Modules\Production\Domain\DailyReports\TaskCosting;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Filament\Resources\DailyReports\DailyReportLayout;
use Modules\Production\Filament\Resources\DailyReports\DailyReportResource;
use Modules\Production\Models\DailyReport;

/**
 * 新規 of 08. 日報入力 (2026-11, mobile first): a panel over the whole screen — on the list and on the dashboard
 * (新規作成) — that asks for the essential fields one step at a time (STEPS: 注番, 機械名 with the job's machines first,
 * 開始 / 終了 / 休憩, 日種別 + 勤務種別, 出来上, 作業人数, 回数, 品質チェック, ケガ), then shows them for a check:
 * この内容で登録 saves, 追加情報を入力 adds the optional fields (DETAIL_FIELDS) first. 作業日 is today and 作業者 the
 * signed-in user unless changed there. Choices advance by themselves; typed values with 次へ / Enter. The rules are those
 * of the record form (BR-PR-05; 終了 after 開始); the figures follow from the model (TaskCosting, 勤務種別 残業 = overtime
 * from 開始 to 終了). Fields the user may not edit (フィールドのアクセス権) are left out and keep their defaults. After the
 * save the list opens with the new record marked (?created=). レコードを再利用する opens it with the copy (?duplicate=).
 */
final class QuickDailyReport extends Component
{
    /** The steps of the essential fields, in order => their fields. */
    public const STEPS = [
        'order' => ['order_id'],
        'machine' => ['machine_id'],
        'start' => ['started_at'],
        'end' => ['ended_at'],
        'break' => ['break_time'],
        'types' => ['day_type', 'work_type'],
        'produced' => ['produced'],
        'crew' => ['crew'],
        'count' => ['changeover_count'],
        'quality' => ['quality_checks'],
        'damage' => ['damaged_before', 'damaged_after'],
    ];

    /** The check of the entered values (この内容で登録 / 追加情報を入力). */
    public const REVIEW = 'review';

    /** 追加情報を入力: the optional fields. */
    public const DETAILS = 'details';

    /** The optional fields, in the order of the record form. */
    public const DETAIL_FIELDS = ['worked_on', 'user_id', 'changeover_minutes', 'cardboard_id', 'cardboard_used', 'partition_id', 'partition_used', 'glue',
        'glue_cost', 'gloves', 'update_status', 'new_status', 'difficulty', 'notes'];

    /** Times offered with one tap. */
    public const TIME_CHOICES = [
        'started_at' => ['08:00', '08:30', '09:00', '13:00'],
        'ended_at' => ['12:00', '17:00', '17:30', '18:00'],
        'break_time' => ['00:00', '00:30', '00:45', '01:00'],
    ];

    /** 作業人数 offered with one tap. */
    public const CREW_CHOICES = ['1', '2', '3', '4'];

    /** Fields chosen with a tap (the step then moves on). */
    private const TAPPED = ['order_id', 'machine_id', 'started_at', 'ended_at', 'break_time', 'crew'];

    private const DECIMALS = ['produced', 'crew', 'changeover_count', 'damaged_before', 'damaged_after', 'changeover_minutes', 'cardboard_used', 'partition_used',
        'glue_cost'];

    #[Locked]
    public string $app = 'daily-report';

    public bool $open = false;

    /** The step shown: a key of STEPS, REVIEW or DETAILS. */
    public string $screen = 'order';

    /** forward | back: the way the step slides in. */
    public string $direction = 'forward';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $orderSearch = '';

    public function mount(string $app = 'daily-report', bool $open = false, ?string $duplicate = null): void
    {
        $this->app = $app;
        $this->data = $this->defaults();
        $this->screen = $this->steps()[0] ?? self::REVIEW;

        if ($open && DailyReportResource::canCreate()) {
            $this->open = true;
            $copy = $this->duplicateSource($duplicate);
            if ($copy !== null) {
                $this->data = $this->copyOf($copy);
                $this->screen = self::REVIEW;
            }
        }
    }

    /** 新規 / 新規作成: a fresh task at the first step. */
    public function start(): void
    {
        abort_unless(DailyReportResource::canCreate(), 403);

        $this->resetErrorBag();
        $this->data = $this->defaults();
        $this->orderSearch = '';
        $this->direction = 'forward';
        $this->screen = $this->steps()[0] ?? self::REVIEW;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    /** The form sent (次へ / Enter, この内容で登録, 登録): the next step, or the save on the check and 追加情報. */
    public function submit(): void
    {
        if (array_key_exists($this->screen, self::STEPS)) {
            $this->next();

            return;
        }

        $this->register();
    }

    /** 次へ (and Enter): the step's values are checked, then the next step (after the last one, the check). */
    public function next(): void
    {
        if (array_key_exists($this->screen, self::STEPS)) {
            $this->check(self::STEPS[$this->screen]);
        }

        $order = [...$this->steps(), self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $this->direction = 'forward';
        $this->screen = $order[$at === false ? 0 : min($at + 1, count($order) - 1)];
    }

    /** 戻る: the step before (from 追加情報, the check). */
    public function back(): void
    {
        $this->resetErrorBag();
        $this->direction = 'back';
        if ($this->screen === self::DETAILS) {
            $this->screen = self::REVIEW;

            return;
        }

        $order = [...$this->steps(), self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $this->screen = $order[$at === false ? 0 : max($at - 1, 0)];
    }

    /** A value of the check tapped: back to its step. */
    public function goTo(string $screen): void
    {
        if (in_array($screen, [...$this->steps(), self::REVIEW, self::DETAILS], true)) {
            $this->resetErrorBag();
            $this->direction = 'back';
            $this->screen = $screen;
        }
    }

    /** A choice tapped (注番, 機械名, a time, 作業人数): kept, and on to the next step. */
    public function choose(string $field, string $value): void
    {
        if (! in_array($field, self::TAPPED, true) || ! $this->editable($field)) {
            return;
        }

        $this->data[$field] = in_array($field, ['order_id', 'machine_id'], true) ? (int) $value : $value;
        $this->next();
    }

    /** 品質チェック: all three at once. */
    public function checkAllQuality(): void
    {
        $this->data['quality_checks'] = ProductionChoices::QUALITY_CHECKS;
    }

    /** 追加情報を入力: the essential values are checked first. */
    public function showDetails(): void
    {
        $this->check($this->stepFields(), jump: true);
        $this->direction = 'forward';
        $this->screen = self::DETAILS;
    }

    /** この内容で登録 / 登録: saved; the list then shows the new record. */
    public function register(): void
    {
        abort_unless(DailyReportResource::canCreate(), 403);

        $this->check([...$this->stepFields(), ...$this->detailFields()], jump: true);

        $report = DB::transaction(fn (): DailyReport => DailyReport::query()->create($this->attributesToSave()));

        $app = DailyReportResource::app();
        $user = auth()->user();
        if ($app === null || $app->record_history_enabled) {
            app(RecordHistory::class)->record($report, $user instanceof User ? $user->id : null);
        }

        Notification::make()->success()->title('登録しました')->send();
        $this->redirect(DailyReportResource::getUrl('index', ['created' => $report->getKey()]));
    }

    /**
     * The essential steps the user may fill in (a step whose fields they may not edit is left out).
     *
     * @return list<string>
     */
    public function steps(): array
    {
        return array_values(array_filter(array_keys(self::STEPS), fn (string $step): bool => array_filter(self::STEPS[$step], $this->editable(...)) !== []));
    }

    /** @return list<string> */
    public function detailFields(): array
    {
        return array_values(array_filter(self::DETAIL_FIELDS, $this->editable(...)));
    }

    public function editable(string $field): bool
    {
        return ($this->levels()[$field] ?? FieldAccessLevel::Edit)->canEdit();
    }

    public function label(string $field): string
    {
        return DailyReportLayout::label($field);
    }

    /**
     * 注番・品名 search: the worker's latest jobs and then the newest ones; typed words find 注番 / 品名.
     *
     * @return list<array{id: int, number: string, product: string}>
     */
    #[Computed]
    public function orderOptions(): array
    {
        $columns = ['id', 'order_number', 'product_name'];
        $search = trim($this->orderSearch);
        if ($search !== '') {
            $orders = self::orders()->select($columns)->whereNotNull('order_number')
                ->where(function (Builder $query) use ($search): void {
                    SearchQuery::where($query, 'order_number', $search);
                    SearchQuery::where($query, 'product_name', $search, 'or');
                })
                ->orderByDesc('id')->limit(8)->get();
        } else {
            $recent = DailyReport::query()->where('user_id', $this->workerId())->whereNotNull('order_id')->groupBy('order_id')
                ->orderByRaw('MAX(id) DESC')->limit(4)->pluck('order_id')->map(fn (mixed $id): int => (int) $id)->all();
            $byId = $recent === [] ? collect() : self::orders()->select($columns)->whereKey($recent)->get()->keyBy('id');
            $orders = collect($recent)->map(fn (int $id): ?Order => $byId->get($id))->filter()
                ->merge(self::orders()->select($columns)->whereNotNull('order_number')->whereKeyNot($recent)->orderByDesc('id')->limit(8 - count($byId))->get());
        }

        return $orders->map(fn (Order $order): array => ['id' => $order->id, 'number' => (string) $order->order_number, 'product' => (string) $order->product_name])
            ->values()->all();
    }

    /** @return array{number: string, product: string, quantity: string, remaining: string, status: string}|null */
    #[Computed]
    public function selectedOrder(): ?array
    {
        $order = is_numeric($this->data['order_id'] ?? null) ? self::orders()->find((int) $this->data['order_id']) : null;

        return $order === null ? null : [
            'number' => (string) $order->order_number,
            'product' => (string) $order->product_name,
            'quantity' => self::format($order->quantity),
            'remaining' => self::format($order->getAttribute('manufacturing_remaining')),
            'status' => (string) $order->status,
        ];
    }

    /**
     * 機械名: the job's machines first (DailyReportLayout::machinesOfOrder), then the rest.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function machineOptions(): array
    {
        return DailyReportLayout::machineOptions(is_numeric($this->data['order_id'] ?? null) ? (int) $this->data['order_id'] : null);
    }

    /**
     * 作業者: the employees 在籍中 (newest first) and the one chosen.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function workerOptions(): array
    {
        return User::query()
            ->where(fn (Builder $query): Builder => $query->where('employment_status', EmploymentStatus::Active->value)->orWhereKey($this->workerId()))
            ->orderByDesc('id')->pluck('name', 'id')->mapWithKeys(fn (mixed $name, int|string $id): array => [(int) $id => (string) $name])->all();
    }

    /**
     * The jobs of 04. 受注入力 the search may offer: those the user may see, or those of their visible reports (security
     * review 2026-11-14 — any job could be found and its 品名 / 数量 read).
     *
     * @return Builder<Order>
     */
    private static function orders(): Builder
    {
        return OrderResource::visibleOr(Order::query(), DailyReportResource::getEloquentQuery()->select('daily_reports.order_id'));
    }

    /** @return array<int, string> ダンボール of 19. 消耗品マスタ */
    #[Computed]
    public function boxOptions(): array
    {
        return Consumable::query()->where('kind', ConsumableKind::Cardboard->value)->orderByDesc('name')->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, int|string $id): array => [(int) $id => (string) $name])->all();
    }

    /**
     * 作業時間 / 残業時間 of the entered times and 勤務種別 (TaskCosting::hours).
     *
     * @return array{work: ?string, overtime: ?string, machine: ?string}
     */
    #[Computed]
    public function hours(): array
    {
        return TaskCosting::hours(($this->data['work_type'] ?? null) === ProductionChoices::WORK_OVERTIME, self::text($this->data['started_at'] ?? null),
            self::text($this->data['ended_at'] ?? null), self::text($this->data['break_time'] ?? null));
    }

    /**
     * The check: label, value and the step it is changed on.
     *
     * @return list<array{label: string, value: string, screen: string}>
     */
    public function summary(): array
    {
        $data = $this->data;
        $value = fn (string $field): string => self::text($data[$field] ?? null) ?? '';
        $hours = $this->hours();
        $rows = [
            'order' => [$this->label('order_id'), ($order = $this->selectedOrder()) !== null ? trim($order['number'].'　'.$order['product']) : ''],
            'machine' => [$this->label('machine_id'), $this->machineOptions()[(int) ($data['machine_id'] ?? 0)] ?? ''],
            'start' => [$this->label('started_at'), $value('started_at')],
            'end' => [$this->label('ended_at'), $value('ended_at')],
            'break' => [$this->label('break_time'), $value('break_time')],
            'types' => [$this->label('day_type').'・'.$this->label('work_type'), $value('day_type').'・'.$value('work_type')],
            'produced' => [$this->label('produced'), self::format($data['produced'] ?? null, '枚')],
            'crew' => [$this->label('crew'), self::format($data['crew'] ?? null, '人')],
            'count' => [$this->label('changeover_count'), self::format($data['changeover_count'] ?? null, '回')],
            'quality' => [$this->label('quality_checks'), implode('、', array_intersect(ProductionChoices::QUALITY_CHECKS, (array) ($data['quality_checks'] ?? [])))],
            'damage' => [$this->label('damaged_before').' / '.$this->label('damaged_after'), self::format($data['damaged_before'] ?? null, '枚').' / '.self::format($data['damaged_after'] ?? null, '枚')],
        ];

        $summary = [];
        foreach ($this->steps() as $step) {
            $summary[] = ['label' => $rows[$step][0], 'value' => $rows[$step][1], 'screen' => $step];
        }
        foreach (['work_hours' => $hours['work'], 'overtime_hours' => $hours['overtime']] as $field => $figure) {
            $summary[] = ['label' => $this->label($field), 'value' => is_numeric($figure) ? self::format($figure, '時間') : '', 'screen' => ''];
        }
        if ($this->editable('worked_on')) {
            $summary[] = ['label' => $this->label('worked_on'), 'value' => $value('worked_on'), 'screen' => self::DETAILS];
        }
        if ($this->editable('user_id')) {
            $summary[] = ['label' => $this->label('user_id'), 'value' => $this->workerOptions()[$this->workerId()] ?? '', 'screen' => self::DETAILS];
        }

        return $summary;
    }

    /**
     * 3 / 11 of the steps (the check and 追加情報 count as done).
     *
     * @return array{at: int, of: int, percent: int}
     */
    public function progress(): array
    {
        $steps = $this->steps();
        $at = array_search($this->screen, $steps, true);
        $done = $at === false ? count($steps) : $at + 1;

        return ['at' => $done, 'of' => count($steps), 'percent' => $steps === [] ? 100 : (int) round($done / count($steps) * 100)];
    }

    public function render(): View
    {
        return view('production::filament.quick-daily-report');
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $user = auth()->user();

        return [
            'order_id' => null, 'machine_id' => null,
            'started_at' => ProductionChoices::DEFAULT_START, 'ended_at' => ProductionChoices::DEFAULT_END, 'break_time' => ProductionChoices::DEFAULT_BREAK,
            'day_type' => ProductionChoices::DAY_TYPES[0], 'work_type' => ProductionChoices::WORK_TYPES[0],
            'produced' => '', 'crew' => ProductionChoices::DEFAULT_CREW, 'changeover_count' => '', 'quality_checks' => [], 'damaged_before' => '0', 'damaged_after' => '0',
            'worked_on' => now()->toDateString(), 'user_id' => $user instanceof User ? $user->id : null,
            'changeover_minutes' => '', 'cardboard_id' => null, 'cardboard_used' => '', 'partition_id' => null, 'partition_used' => '', 'glue' => null, 'glue_cost' => '',
            'gloves' => false, 'update_status' => false, 'new_status' => null, 'difficulty' => null, 'notes' => '',
        ];
    }

    /**
     * レコードを再利用する: the record to copy — visible, and 「レコードの再利用」 on.
     */
    private function duplicateSource(?string $id): ?DailyReport
    {
        $app = DailyReportResource::app();
        if ($id === null || preg_match('/^\d+$/', $id) !== 1 || ($app !== null && ! $app->record_duplicate_enabled)) {
            return null;
        }

        $source = DailyReportResource::getEloquentQuery()->whereKey((int) $id)->first();

        return $source instanceof DailyReport && DailyReportResource::canView($source) ? $source : null;
    }

    /**
     * The copy: the task's entered values, for today and without the status update (as the record form's copy).
     *
     * @return array<string, mixed>
     */
    private function copyOf(DailyReport $source): array
    {
        $data = $this->defaults();
        foreach (array_keys($data) as $field) {
            if (in_array($field, ['worked_on', 'update_status', 'new_status'], true) || ! $this->editable($field)) {
                continue;
            }
            $value = $source->getAttribute($field);
            $data[$field] = match (true) {
                in_array($field, ['started_at', 'ended_at', 'break_time'], true) => is_string($value) ? substr($value, 0, 5) : $data[$field],
                in_array($field, self::DECIMALS, true) => is_numeric($value) ? self::plain((string) $value) : '',
                $field === 'quality_checks' => array_values((array) $value),
                $field === 'gloves' => (bool) $value,
                default => $value ?? $data[$field],
            };
        }

        return $data;
    }

    /**
     * Validates the fields (those the user may edit); with $jump the step of the first error is shown.
     *
     * @param  list<string>  $fields
     */
    private function check(array $fields, bool $jump = false): void
    {
        $rules = [];
        foreach ($this->fieldRules() as $key => $rule) {
            $field = explode('.', $key)[1];
            if (in_array($field, $fields, true) && $this->editable($field)) {
                $rules[$key] = $rule;
            }
        }

        try {
            $this->validate($rules, [], $this->validationLabels());
        } catch (ValidationException $exception) {
            if ($jump) {
                $field = explode('.', (string) array_key_first($exception->errors()))[1] ?? '';
                $this->direction = 'back';
                $this->screen = $this->screenOf($field);
            }

            throw $exception;
        }
    }

    /** @return array<string, list<mixed>> the rules of the record form (DailyReportLayout), on `data.<field>` */
    private function fieldRules(): array
    {
        $number = ['nullable', 'numeric', 'decimal:0,4', 'min:-999999999999', 'max:999999999999'];
        $required = ['required', 'numeric', 'decimal:0,4', 'min:-999999999999', 'max:999999999999'];
        $boxes = Rule::exists('consumables', 'id')->where('kind', ConsumableKind::Cardboard->value);

        return [
            'data.order_id' => ['required', 'integer', Rule::exists((new Order)->getTable(), 'id'),
                new VisibleChoice(OrderResource::class, Order::class, fn (): Builder => DailyReportResource::getEloquentQuery()->select('daily_reports.order_id'))],
            'data.machine_id' => ['required', 'integer', Rule::exists((new Machine)->getTable(), 'id')],
            'data.started_at' => ['required', 'date_format:H:i'],
            'data.ended_at' => ['required', 'date_format:H:i', $this->afterStart(...)],
            'data.break_time' => ['nullable', 'date_format:H:i'],
            'data.day_type' => ['required', Rule::in(ProductionChoices::DAY_TYPES)],
            'data.work_type' => ['required', Rule::in(ProductionChoices::WORK_TYPES)],
            'data.produced' => $required,
            'data.crew' => ['required', 'numeric', 'decimal:0,4', 'min:1', 'max:999999999999'],
            'data.changeover_count' => $number,
            'data.quality_checks' => ['required', 'array', 'min:1'],
            'data.quality_checks.*' => [Rule::in(ProductionChoices::QUALITY_CHECKS)],
            'data.damaged_before' => $required,
            'data.damaged_after' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:999999999999'],
            'data.worked_on' => ['required', 'date_format:Y-m-d'],
            'data.user_id' => ['required', 'integer', Rule::exists((new User)->getTable(), 'id')],
            'data.changeover_minutes' => $number,
            'data.cardboard_id' => ['nullable', 'integer', $boxes],
            'data.cardboard_used' => $number,
            'data.partition_id' => ['nullable', 'integer', $boxes],
            'data.partition_used' => $number,
            'data.glue' => ['nullable', Rule::in(ProductionChoices::GLUES)],
            'data.glue_cost' => $number,
            'data.gloves' => ['boolean'],
            'data.update_status' => ['boolean'],
            'data.new_status' => ['nullable', Rule::in(OrderChoices::STATUSES)],
            'data.difficulty' => ['nullable', Rule::in(ProductionChoices::DIFFICULTIES)],
            'data.notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** 終了時間 after 開始時間 (a task within the day). */
    private function afterStart(string $attribute, mixed $value, Closure $fail): void
    {
        $start = TaskCosting::seconds(self::text($this->data['started_at'] ?? null));
        $end = TaskCosting::seconds(self::text($value));
        if ($start !== null && $end !== null && $end <= $start) {
            $fail($this->label('ended_at').'は'.$this->label('started_at').'より後の時刻を入力してください。');
        }
    }

    /** @return array<string, string> */
    private function validationLabels(): array
    {
        $labels = [];
        foreach ([...array_merge(...array_values(self::STEPS)), ...self::DETAIL_FIELDS] as $field) {
            $labels['data.'.$field] = $this->label($field);
        }
        $labels['data.quality_checks.*'] = $this->label('quality_checks');

        return $labels;
    }

    private function screenOf(string $field): string
    {
        foreach (self::STEPS as $step => $fields) {
            if (in_array($field, $fields, true)) {
                return $step;
            }
        }

        return in_array($field, self::DETAIL_FIELDS, true) ? self::DETAILS : self::REVIEW;
    }

    /** @return list<string> */
    private function stepFields(): array
    {
        return array_merge(...array_values(self::STEPS));
    }

    /**
     * The task as saved: blanks as null; fields the user may not edit keep their defaults.
     *
     * @return array<string, mixed>
     */
    private function attributesToSave(): array
    {
        $defaults = $this->defaults();
        $data = [];
        foreach ($defaults as $field => $default) {
            $data[$field] = $this->editable($field) ? ($this->data[$field] ?? $default) : $default;
        }

        $id = fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null;
        $blank = fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value;
        $update = (bool) $data['update_status'];

        return [
            'worked_on' => $data['worked_on'],
            'user_id' => $id($data['user_id']),
            'order_id' => $id($data['order_id']),
            'machine_id' => $id($data['machine_id']),
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'],
            'break_time' => $blank($data['break_time']),
            'day_type' => $data['day_type'],
            'work_type' => $data['work_type'],
            ...array_map($blank, array_intersect_key($data, array_flip(self::DECIMALS))),
            'quality_checks' => array_values(array_intersect(ProductionChoices::QUALITY_CHECKS, (array) $data['quality_checks'])),
            'cardboard_id' => $id($data['cardboard_id']),
            'partition_id' => $id($data['partition_id']),
            'glue' => $blank($data['glue']),
            'gloves' => (bool) $data['gloves'],
            'update_status' => $update,
            'new_status' => $blank($data['new_status']),
            'difficulty' => $blank($data['difficulty']),
            'notes' => $blank($data['notes']),
        ];
    }

    private function workerId(): ?int
    {
        return is_numeric($this->data['user_id'] ?? null) ? (int) $this->data['user_id'] : null;
    }

    /** @return array<string, FieldAccessLevel> */
    private function levels(): array
    {
        $user = auth()->user();
        $app = DailyReportResource::app();

        return $user instanceof User && $app !== null ? FieldAccess::levels($user, $app) : [];
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** 「3,000枚」 */
    private static function format(mixed $value, string $unit = ''): string
    {
        return is_numeric($value) ? number_format((float) $value, str_contains(self::plain((string) $value), '.') ? strlen(explode('.', self::plain((string) $value))[1]) : 0).$unit : '';
    }

    /** 800.0000 → 800, 1.5000 → 1.5 */
    private static function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
