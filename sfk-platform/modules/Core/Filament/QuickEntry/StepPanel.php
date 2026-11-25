<?php

declare(strict_types=1);

namespace Modules\Core\Filament\QuickEntry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Filament\AppResource;

/**
 * The walk of a quick-entry panel (QuickEntry and 08. 日報入力's QuickDailyReport): one step at a time, 次へ / Enter
 * checks the step and moves on, 戻る goes back, then the check — where a tap on a row opens its step and 次へ there
 * returns to the check — and 追加情報 (the optional fields). Fields the user may not edit (フィールドのアクセス権) are not
 * asked; a failed save shows the step of the first error. Opened with ?new=1 (再利用: ?duplicate=<id>, at the check).
 *
 * A panel gives its resource, its steps and their fields, the optional fields, the defaults, the rules and the save.
 */
abstract class StepPanel extends Component
{
    /** The check of the entered values. */
    public const REVIEW = 'review';

    /** 追加情報を入力: the optional fields. */
    public const DETAILS = 'details';

    #[Locked]
    public string $app = '';

    public bool $open = false;

    /** The screen: a step key, REVIEW or DETAILS. */
    public string $screen = '';

    /** forward | back: the way the step slides in. */
    public string $direction = 'forward';

    /** @var array<string, mixed> */
    public array $data = [];

    /** The step was opened from the check: 次へ goes back there. */
    public bool $fromReview = false;

    /**
     * The app's resource.
     *
     * @return class-string<AppResource>
     */
    abstract public static function resource(): string;

    /**
     * The steps asked, in order (those with a field the user may edit).
     *
     * @return list<string>
     */
    abstract public function steps(): array;

    /** この内容で登録 / 登録: checked, saved, then the way to the new record. */
    abstract public function register(): void;

    /**
     * The fields of a step the user may edit.
     *
     * @return list<string>
     */
    abstract protected function stepFields(string $step): array;

    /**
     * The optional fields (追加情報) the user may edit.
     *
     * @return list<string>
     */
    abstract protected function detailFieldNames(): array;

    /** @return array<string, mixed> every field the entry saves, with its starting value */
    abstract protected function defaults(): array;

    /** @return array<string, mixed> the rules of the record form, on `data.<field>` (tables: `data.<field>.*.<column>`) */
    abstract protected function validationRules(): array;

    /** @return array<string, string> the names of the fields in the validation messages */
    abstract protected function validationLabels(): array;

    /** @return array<string, string> messages in place of the standard ones */
    protected function validationMessages(): array
    {
        return [];
    }

    /**
     * レコードを再利用する: the values of the copy.
     *
     * @return array<string, mixed>
     */
    protected function copyOf(Model $source): array
    {
        return $this->defaults();
    }

    /** A screen about to be shown (e.g. a step's search emptied). */
    protected function showing(string $screen, string $direction): void {}

    public function mount(string $app = '', bool $open = false, ?string $duplicate = null): void
    {
        $resource = static::resource();
        $this->app = $app !== '' ? $app : $resource::appCode();
        $this->data = $this->defaults();

        // closed, nothing of the steps is built (their lists read the database); start() picks the first one (speed)
        if ($open && $resource::canCreate()) {
            $this->open = true;
            $source = $this->duplicateSource($duplicate);
            if ($source !== null) {
                $this->data = [...$this->defaults(), ...$this->copyOf($source)];
                $this->screen = self::REVIEW;
            } else {
                $this->show($this->steps()[0] ?? self::REVIEW, 'forward');
            }
        }
    }

    /** 新規 / 新規作成: a fresh entry at the first step. */
    public function start(): void
    {
        abort_unless(static::resource()::canCreate(), 403);

        $this->resetErrorBag();
        $this->data = $this->defaults();
        $this->fromReview = false;
        $this->show($this->steps()[0] ?? self::REVIEW, 'forward');
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

    /** 戻る: the step before (from 追加情報, or a step opened from the check: the check). */
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

    /** 追加情報を入力: the steps are checked first. */
    public function showDetails(): void
    {
        $this->check($this->askedFields(), jump: true);
        $this->show(self::DETAILS, 'forward');
    }

    public function isStep(string $screen): bool
    {
        return in_array($screen, $this->steps(), true);
    }

    public function editable(string $field): bool
    {
        return ($this->levels()[$field] ?? FieldAccessLevel::Edit)->canEdit();
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

    protected function show(string $screen, string $direction): void
    {
        $this->direction = $direction;
        if ($screen === self::REVIEW) {
            $this->fromReview = false;
        }
        $this->showing($screen, $direction);
        $this->screen = $screen;
    }

    /** @return list<string> the fields of the steps asked */
    protected function askedFields(): array
    {
        return array_merge([], ...array_map(fn (string $step): array => $this->stepFields($step), $this->steps()));
    }

    /**
     * Validates the fields the user may edit; with $jump the screen of the first error is shown.
     *
     * @param  list<string>  $fields
     */
    protected function check(array $fields, bool $jump = false): void
    {
        $rules = [];
        foreach ($this->validationRules() as $key => $rule) {
            $field = explode('.', $key)[1] ?? '';
            if (in_array($field, $fields, true) && $this->editable($field)) {
                $rules[$key] = $rule;
            }
        }

        try {
            $this->validate($rules, $this->validationMessages(), $this->validationLabels());
        } catch (ValidationException $exception) {
            if ($jump) {
                $field = explode('.', (string) array_key_first($exception->errors()))[1] ?? '';
                $this->show($this->screenOf($field), 'back');
            }

            throw $exception;
        }
    }

    private function screenOf(string $field): string
    {
        foreach ($this->steps() as $step) {
            if (in_array($field, $this->stepFields($step), true)) {
                return $step;
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
