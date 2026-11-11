<?php

declare(strict_types=1);

namespace Modules\Core\Filament\QuickEntry;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Search\SearchText;
use Modules\Core\Filament\AppResource;

/**
 * 新規 of an app as a quick entry (user, 2026-11-09; the design of 08. 日報入力's QuickDailyReport): a panel over the whole
 * screen that asks one thing at a time — choices are tapped and move on by themselves, typed values go on with 次へ / Enter,
 * 戻る goes back — then shows everything on a check where a tap on a row goes back to that step (and 次へ there returns to
 * the check). この内容で登録 saves; 追加情報を入力 adds the optional fields first. An app returns it from
 * AppResource::quickCreateComponent() (no create page): Core opens it from 新規, the dashboard's 新規作成 and ?new=1
 * (再利用: ?duplicate=<id>).
 *
 * An app gives its steps (QuickStep with QuickField inputs), the optional fields, the defaults, the rules (those of the
 * record form, on `data.<field>`), the labels and how the record is created; the engine keeps the order, the progress,
 * フィールドのアクセス権 (fields the user may not edit are not asked and keep their defaults), the check of each step
 * (the first error's step is shown), レコードを再利用する, 変更履歴 and the way to the saved record.
 */
abstract class QuickEntry extends Component
{
    /** The check of the entered values. */
    public const REVIEW = 'review';

    /** 追加情報を入力: the optional fields. */
    public const DETAILS = 'details';

    /** How many people / records a list shows at once (the search narrows them). */
    public const LIST_LIMIT = 40;

    #[Locked]
    public string $app = '';

    public bool $open = false;

    /** The screen: a step key, REVIEW or DETAILS. */
    public string $screen = '';

    /** forward | back: the way the step slides in. */
    public string $direction = 'forward';

    /** @var array<string, mixed> */
    public array $data = [];

    /** The search box of a step with a list (people, records); emptied on every new step. */
    public string $search = '';

    /** The step was opened from the check: 次へ goes back there. */
    public bool $fromReview = false;

    /** @var array<string, QuickStep>|null the steps of the values below (their options may follow earlier answers) */
    private ?array $stepCache = null;

    private string $stepCacheKey = '';

    /**
     * The app's resource.
     *
     * @return class-string<AppResource>
     */
    abstract public static function resource(): string;

    /** The title of the panel, e.g. 「事故報告を追加」. */
    abstract public function heading(): string;

    public function label(string $field): string
    {
        return $field;
    }

    /** The label on the check (e.g. with the block a field belongs to, where two share a label). */
    public function reviewLabel(string $field): string
    {
        return $this->label($field);
    }

    /** @return list<QuickStep> the steps, in order */
    abstract protected function stepList(): array;

    /** @return list<QuickField> the optional fields (追加情報を入力), in the order of the record form */
    protected function detailList(): array
    {
        return [];
    }

    /** @return array<string, mixed> every field the entry saves, with its starting value */
    abstract protected function defaults(): array;

    /** @return array<string, mixed> the rules of the record form, on `data.<field>` (tables: `data.<field>.*.<column>`) */
    abstract protected function rules(): array;

    /**
     * Creates the record with its related rows (called inside a transaction).
     *
     * @param  array<string, mixed>  $values  the defaults with what the user may and did enter
     */
    abstract protected function create(array $values): Model;

    /**
     * レコードを再利用する: the values of the copy.
     *
     * @return array<string, mixed>
     */
    protected function copyOf(Model $source): array
    {
        return $this->defaults();
    }

    /** After the save (e.g. notices that need the related rows). */
    protected function afterCreate(Model $record): void {}

    /** A step shown going forward (e.g. 運転後 takes over the vehicle of 運転前). */
    protected function entering(string $step): void {}

    /** A value set by a tap (e.g. 部署 → the items of the checklist). */
    protected function chosen(string $field): void {}

    /**
     * The records a lookup offers for the search.
     *
     * @return list<array{value: int|string, label: string, sub: string}>
     */
    public function lookupOptions(string $field, string $search): array
    {
        return [];
    }

    /**
     * The chosen record of a lookup, shown as a card above the search.
     *
     * @return array{label: string, sub: string, facts: array<string, string>}|null
     */
    public function lookupSelected(string $field): ?array
    {
        return null;
    }

    /** The value of a custom field on the check. */
    protected function customSummary(string $field): string
    {
        return '';
    }

    /**
     * Rows of the check after those of the steps (figures that follow from the entry, e.g. 合計損害額).
     *
     * @return list<array{label: string, value: string, screen: string, invalid: bool}>
     */
    protected function extraSummary(): array
    {
        return [];
    }

    /**
     * Messages of the validation in place of the standard ones: those of the app's RecordRules.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $rules = static::resource()::recordRules();

        return $rules === null ? [] : self::prefixed($rules->messages());
    }

    /**
     * The app's RecordRules (AppResource::recordRules(): the rules of every screen of the app) on `data.<path>`.
     *
     * @return array<string, mixed>
     */
    protected function recordRules(): array
    {
        $rules = static::resource()::recordRules();

        return $rules === null ? [] : self::prefixed($rules->rules($this->data, null));
    }

    /**
     * @param  array<string, T>  $byPath
     * @return array<string, T>
     *
     * @template T
     */
    private static function prefixed(array $byPath): array
    {
        $prefixed = [];
        foreach ($byPath as $path => $value) {
            $prefixed['data.'.$path] = $value;
        }

        return $prefixed;
    }

    /**
     * Labels of the validation messages beyond `data.<field>` (e.g. the columns of a table).
     *
     * @return array<string, string>
     */
    protected function extraValidationLabels(): array
    {
        return [];
    }

    public function mount(string $app = '', bool $open = false, ?string $duplicate = null): void
    {
        $resource = static::resource();
        $this->app = $app !== '' ? $app : $resource::appCode();
        $this->data = $this->defaults();

        // closed, nothing of the steps is built (their lists read the database); start() picks the first one (speed)
        if ($open && $resource::canCreate()) {
            $this->screen = $this->steps()[0] ?? self::REVIEW;
            $this->open = true;
            $source = $this->duplicateSource($duplicate);
            if ($source !== null) {
                $this->data = [...$this->defaults(), ...$this->copyOf($source)];
                $this->screen = self::REVIEW;
            } else {
                $this->entering($this->screen);
            }
        }
    }

    /** 新規 / 新規作成: a fresh entry at the first step. */
    public function start(): void
    {
        abort_unless(static::resource()::canCreate(), 403);

        $this->resetErrorBag();
        $this->data = $this->defaults();
        $this->search = '';
        $this->fromReview = false;
        $this->direction = 'forward';
        $this->screen = $this->steps()[0] ?? self::REVIEW;
        $this->entering($this->screen);
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
        if ($this->isStep($this->screen)) {
            $this->next();

            return;
        }

        $this->register();
    }

    /** 次へ (and Enter): the step's values are checked, then the next step — or the check, when it was opened from there. */
    public function next(): void
    {
        if ($this->isStep($this->screen)) {
            $this->check($this->stepFields($this->screen));
        }

        $order = [...$this->steps(), self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $to = $this->fromReview ? self::REVIEW : $order[$at === false ? 0 : min($at + 1, count($order) - 1)];
        $this->show($to, 'forward');
    }

    /** 戻る: the step before (from 追加情報, the check). */
    public function back(): void
    {
        $this->resetErrorBag();
        if ($this->screen === self::DETAILS || $this->fromReview) {
            $this->show(self::REVIEW, 'back');

            return;
        }

        $order = [...$this->steps(), self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $this->show($order[$at === false ? 0 : max($at - 1, 0)], 'back');
    }

    /** A row of the check tapped: its step (次へ there comes back to the check). */
    public function goTo(string $screen): void
    {
        if ($screen === self::DETAILS || $this->isStep($screen)) {
            $this->resetErrorBag();
            $from = $this->screen;
            $this->show($screen, 'back');
            $this->fromReview = $from === self::REVIEW && $screen !== self::DETAILS;
        }
    }

    /** A choice, a person or a record tapped: kept, and on to the next step when it is the step's only field. */
    public function choose(string $field, string $value): void
    {
        $input = $this->fieldOnScreen($field);
        if ($input === null || ! in_array($input->kind, ['choice', 'person', 'lookup'], true) || ! $this->editable($field)) {
            return;
        }

        $this->data[$field] = $this->typed($input, $value);
        $this->resetErrorBag('data.'.$field);
        $this->chosen($field);

        if ($this->isStep($this->screen) && $this->stepOf($this->screen)->advancesOnTap()) {
            $this->next();
        }
    }

    /** A chip of a date, a time or a number: the value is filled in (and the step moves on when it is its only field). */
    public function useChip(string $field, string $value): void
    {
        $input = $this->fieldOnScreen($field);
        if ($input === null || ! array_key_exists($value, $input->chips) || ! $this->editable($field)) {
            return;
        }

        $this->data[$field] = $value;
        $this->resetErrorBag('data.'.$field);
        if ($this->isStep($this->screen) && count($this->stepOf($this->screen)->fields) === 1) {
            $this->next();
        }
    }

    /** クリア of an optional choice. */
    public function clear(string $field): void
    {
        if ($this->fieldOnScreen($field) !== null && $this->editable($field)) {
            $this->data[$field] = $this->defaults()[$field] ?? null;
            $this->chosen($field);
        }
    }

    /** The button of a step that skips the rest (QuickStep::skip()): its fields emptied, then the check. */
    public function skip(): void
    {
        $step = $this->isStep($this->screen) ? $this->stepOf($this->screen) : null;
        if ($step?->skip === null) {
            return;
        }

        $defaults = $this->defaults();
        foreach ($step->skip['fields'] as $field) {
            $this->data[$field] = array_key_exists($field, $defaults) ? $defaults[$field] : null;
        }
        $this->resetErrorBag();
        $this->show(self::REVIEW, 'forward');
    }

    /** 追加情報を入力: the steps are checked first. */
    public function showDetails(): void
    {
        $this->check($this->askedFields(), jump: true);
        $this->show(self::DETAILS, 'forward');
    }

    /** この内容で登録 / 登録: saved, then the new record is shown. */
    public function register(): void
    {
        $resource = static::resource();
        abort_unless($resource::canCreate(), 403);

        $this->check([...$this->askedFields(), ...$this->detailFieldNames()], jump: true);

        $values = $this->values();
        $record = DB::transaction(fn (): Model => $this->create($values));

        $app = $resource::app();
        $user = auth()->user();
        if ($record instanceof HasRecordHistory && ($app === null || $app->record_history_enabled)) {
            app(RecordHistory::class)->record($record, $user instanceof User ? $user->id : null);
        }
        $this->afterCreate($record);

        Notification::make()->success()->title('登録しました')->send();
        $this->redirect($resource::hasPage('view') && $resource::canView($record)
            ? $resource::getUrl('view', ['record' => $record])
            : $resource::getUrl('index', ['created' => $record->getKey()]));
    }

    /**
     * The steps asked: those whose condition holds and with a field the user may edit.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        $steps = [];
        foreach ($this->stepMap() as $key => $step) {
            if ($step->isAsked($this->data) && array_filter($step->fieldNames(), $this->editable(...)) !== []) {
                $steps[] = $key;
            }
        }

        return $steps;
    }

    public function stepOf(string $key): QuickStep
    {
        return $this->stepMap()[$key];
    }

    /** @return list<QuickField> the fields of the step the user may edit */
    public function fieldsOf(string $key): array
    {
        return array_values(array_filter($this->stepOf($key)->fields, fn (QuickField $field): bool => $this->editable($field->name)));
    }

    public function isStep(string $screen): bool
    {
        return in_array($screen, $this->steps(), true);
    }

    /** @return list<QuickField> the optional fields the user may edit */
    public function details(): array
    {
        return array_values(array_filter($this->detailList(), fn (QuickField $field): bool => $this->editable($field->name)));
    }

    public function editable(string $field): bool
    {
        return ($this->levels()[$field] ?? FieldAccessLevel::Edit)->canEdit();
    }

    /** The title of the screen. */
    public function screenTitle(): string
    {
        return match (true) {
            $this->screen === self::REVIEW => '確認',
            $this->screen === self::DETAILS => '追加情報',
            $this->isStep($this->screen) => $this->stepOf($this->screen)->title,
            default => '',
        };
    }

    /**
     * The people of a person / people field matching the search (the chosen ones first).
     *
     * @return array<int|string, string>
     */
    public function listed(QuickField $field): array
    {
        $chosen = array_map('strval', (array) ($this->data[$field->name] ?? []));
        $words = SearchText::words(SearchText::normalize($this->search));
        $matches = array_filter($field->options, function (string $label) use ($words): bool {
            $text = SearchText::normalize($label);
            foreach ($words as $word) {
                if (! str_contains($text, $word)) {
                    return false;
                }
            }

            return true;
        });

        $first = array_intersect_key($field->options, array_flip($chosen));

        return array_slice($first + $matches, 0, self::LIST_LIMIT, true);
    }

    /**
     * The check: label, value and the screen it is changed on.
     *
     * @return list<array{label: string, value: string, screen: string, invalid: bool}>
     */
    public function summary(): array
    {
        $rows = [];
        foreach ($this->steps() as $key) {
            foreach ($this->fieldsOf($key) as $field) {
                $value = $this->display($field);
                $rows[] = ['label' => $this->reviewLabel($field->name), 'value' => $value, 'screen' => $key, 'invalid' => $field->required && $value === ''];
            }
        }
        foreach ($this->details() as $field) {
            if ($field->inReview) {
                $value = $this->display($field);
                $rows[] = ['label' => $this->reviewLabel($field->name), 'value' => $value, 'screen' => self::DETAILS, 'invalid' => $field->required && $value === ''];
            }
        }

        return [...$rows, ...$this->extraSummary()];
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

    /** The value of a field as written on the check. */
    public function display(QuickField $field): string
    {
        $value = $this->data[$field->name] ?? null;

        return match ($field->kind) {
            'choice', 'person', 'select' => is_int($value) || (is_string($value) && $value !== '') ? ($field->options[$value] ?? (string) $value) : '',
            'choices', 'people' => implode('、', array_values(array_intersect_key($field->options, array_flip(array_map('strval', (array) $value))))),
            'lookup' => $this->lookupSelected($field->name)['label'] ?? '',
            'number' => self::number($value, $field->unit),
            'custom' => $this->customSummary($field->name),
            default => is_scalar($value) ? trim((string) $value) : '',
        };
    }

    public function render(): View
    {
        return view('core::filament.quick-entry');
    }

    /** 「3,000枚」, 「1.5」; blank for no number */
    public static function number(mixed $value, string $unit = ''): string
    {
        if (! is_numeric($value)) {
            return '';
        }
        $plain = str_contains((string) $value, '.') ? rtrim(rtrim((string) $value, '0'), '.') : (string) $value;
        $decimals = str_contains($plain, '.') ? strlen(explode('.', $plain)[1]) : 0;

        return number_format((float) $plain, $decimals).$unit;
    }

    /** A blank text as null (the record stores no empty strings). */
    protected static function blank(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    protected static function toId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** The signed-in user's id. */
    protected static function userId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->id : null;
    }

    /**
     * The values saved: the defaults, with what the user may edit and was asked (a step not asked keeps its defaults).
     *
     * @return array<string, mixed>
     */
    protected function values(): array
    {
        $asked = [...$this->askedFields(), ...$this->detailFieldNames()];
        $values = [];
        foreach ($this->defaults() as $field => $default) {
            $values[$field] = in_array($field, $asked, true) && $this->editable($field) ? ($this->data[$field] ?? $default) : $default;
        }

        return $values;
    }

    /** @return array<string, QuickStep> */
    private function stepMap(): array
    {
        $key = md5((string) json_encode($this->data));
        if ($this->stepCache === null || $this->stepCacheKey !== $key) {
            $this->stepCacheKey = $key;
            $this->stepCache = [];
            foreach ($this->stepList() as $step) {
                $this->stepCache[$step->key] = $step;
            }
        }

        return $this->stepCache;
    }

    private function show(string $screen, string $direction): void
    {
        $this->direction = $direction;
        $this->search = '';
        if ($screen === self::REVIEW) {
            $this->fromReview = false;
        }
        if ($direction === 'forward' && $this->isStep($screen)) {
            $this->entering($screen);
        }
        $this->screen = $screen;
    }

    private function fieldOnScreen(string $name): ?QuickField
    {
        $fields = match (true) {
            $this->screen === self::DETAILS => $this->details(),
            $this->isStep($this->screen) => $this->fieldsOf($this->screen),
            default => [],
        };
        foreach ($fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    private function typed(QuickField $field, string $value): int|string|null
    {
        if ($value === '') {
            return null;
        }

        return is_int(array_key_first($field->options)) || in_array($field->kind, ['person', 'lookup'], true) ? (int) $value : $value;
    }

    /** @return list<string> the fields of the steps asked */
    private function askedFields(): array
    {
        return array_merge([], ...array_map(fn (string $key): array => $this->stepFields($key), $this->steps()));
    }

    /** @return list<string> */
    private function stepFields(string $key): array
    {
        return array_map(fn (QuickField $field): string => $field->name, $this->fieldsOf($key));
    }

    /** @return list<string> */
    private function detailFieldNames(): array
    {
        return array_map(fn (QuickField $field): string => $field->name, $this->details());
    }

    /**
     * Validates the fields; with $jump the screen of the first error is shown.
     *
     * @param  list<string>  $fields
     */
    private function check(array $fields, bool $jump = false): void
    {
        $rules = [];
        foreach ($this->rules() as $key => $rule) {
            $field = explode('.', $key)[1] ?? '';
            if (in_array($field, $fields, true)) {
                $rules[$key] = $rule;
            }
        }

        try {
            $this->validate($rules, $this->messages(), $this->validationLabels());
        } catch (ValidationException $exception) {
            if ($jump) {
                $field = explode('.', (string) array_key_first($exception->errors()))[1] ?? '';
                $this->show($this->screenOf($field), 'back');
            }

            throw $exception;
        }
    }

    /** @return array<string, string> */
    private function validationLabels(): array
    {
        $labels = [];
        foreach ([...$this->stepMap(), ...[QuickStep::make(self::DETAILS, '', $this->detailList())]] as $step) {
            foreach ($step->fieldNames() as $field) {
                $labels['data.'.$field] = $this->label($field);
                $labels['data.'.$field.'.*'] = $this->label($field);
            }
        }

        $rules = static::resource()::recordRules();

        return [...$labels, ...($rules === null ? [] : self::prefixed($rules->attributes())), ...$this->extraValidationLabels()];
    }

    private function screenOf(string $field): string
    {
        foreach ($this->steps() as $key) {
            if (in_array($field, $this->stepFields($key), true)) {
                return $key;
            }
        }

        return in_array($field, $this->detailFieldNames(), true) ? self::DETAILS : self::REVIEW;
    }

    /** レコードを再利用する: the record to copy — visible, and 「レコードの再利用」 on. */
    private function duplicateSource(?string $id): ?Model
    {
        $resource = static::resource();
        $app = $resource::app();
        if ($id === null || preg_match('/^\d+$/', $id) !== 1 || ($app !== null && ! $app->record_duplicate_enabled)) {
            return null;
        }

        $source = $resource::getEloquentQuery()->whereKey((int) $id)->first();

        return $source instanceof Model && $resource::canView($source) ? $source : null;
    }

    /** @return array<string, FieldAccessLevel> */
    private function levels(): array
    {
        $user = auth()->user();
        $app = static::resource()::app();

        return $user instanceof User && $app !== null ? FieldAccess::levels($user, $app) : [];
    }
}
