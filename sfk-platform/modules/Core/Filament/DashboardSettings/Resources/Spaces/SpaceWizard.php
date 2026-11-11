<?php

declare(strict_types=1);

namespace Modules\Core\Filament\DashboardSettings\Resources\Spaces;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Core\Access\UserType;
use Modules\Core\Application\Apps\AppActionNotAllowed;
use Modules\Core\Application\Apps\SpaceService;
use Modules\Core\Domain\Search\SearchText;
use Modules\Core\Filament\DashboardSettings\DashboardSettingsPlugin;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Space;

/**
 * スペースを作成 / 編集 as a wizard (user, 2026-11-11), made for the computer (core::filament.space-wizard): a window over
 * the screen with the steps — スペース名, メンバー, アプリ, 確認 — in a bar on top (a step reached once can be clicked),
 * the choices in columns and what is chosen in a side panel (× takes it out); phones get it over the whole screen.
 * メンバー are clicked on and off: 従業員カテゴリ (all their employees, also those who get the category later —
 * SpaceMembership) and employees by name; アプリ the same. 表示中をすべて選択 / 選択を解除 act on what the search shows.
 * Editing opens on 確認, where 変更 goes to a step (次へ there comes back). The space's アクセス権 stay on SpaceResource
 * (its own dialog).
 *
 * Opened by the event OPEN_EVENT (`space`: id, or none for a new one) from ManageSpaces, or from the start with
 * `?space=<id>` (⚙ of a space in the dashboard's アプリ panel). Administrators only (SpaceService checks it again).
 */
final class SpaceWizard extends Component
{
    public const OPEN_EVENT = 'sfk-space-wizard';

    public const SAVED_EVENT = 'sfk-space-saved';

    public const REVIEW = 'review';

    /** @var list<string> the steps, in order */
    public const STEPS = ['name', 'members', 'apps'];

    #[Locked]
    public string $panel = '';

    #[Locked]
    public ?int $spaceId = null;

    public bool $open = false;

    /** A step of STEPS or REVIEW. */
    public string $screen = 'name';

    /** forward | back: the way the step slides in. */
    public string $direction = 'forward';

    /** The step was opened from the check: 次へ goes back there. */
    public bool $fromReview = false;

    /** The furthest step reached (index in STEPS + REVIEW): the step bar opens those up to it. */
    public int $reached = 0;

    /** The search box of メンバー / アプリ; emptied on every new step. */
    public string $search = '';

    public string $name = '';

    /** @var list<int> the members added by name */
    public array $users = [];

    /** @var list<int> the 従業員カテゴリ whose employees are members */
    public array $categories = [];

    /** @var list<int> */
    public array $apps = [];

    public function mount(?int $space = null): void
    {
        $this->panel = DashboardSettingsPlugin::currentPanelId();
        if ($space !== null && self::allowed()) {
            $this->load($space);
        }
    }

    /** スペースを作成 (no space) or ✎ of a space. */
    #[On(self::OPEN_EVENT)]
    public function start(?int $space = null): void
    {
        abort_unless(self::allowed(), 403);

        $this->resetErrorBag();
        $this->search = '';
        $this->fromReview = false;
        $this->direction = 'forward';

        if ($space !== null) {
            $this->load($space);

            return;
        }

        $this->spaceId = null;
        $this->name = '';
        $this->users = $this->categories = $this->apps = [];
        $this->screen = self::STEPS[0];
        $this->reached = 0;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    /** The form sent (次へ / Enter, 保存). */
    public function submit(): void
    {
        $this->screen === self::REVIEW ? $this->save() : $this->next();
    }

    /** 次へ: the step's values are checked, then the next step — or the check, when it was opened from there. */
    public function next(): void
    {
        if (! $this->checkStep($this->screen)) {
            return;
        }

        $order = [...self::STEPS, self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $this->show($this->fromReview ? self::REVIEW : $order[$at === false ? 0 : min($at + 1, count($order) - 1)], 'forward');
    }

    /** 戻る: the step before (from a step opened on the check: the check). */
    public function back(): void
    {
        $this->resetErrorBag();
        if ($this->fromReview) {
            $this->show(self::REVIEW, 'back');

            return;
        }

        $order = [...self::STEPS, self::REVIEW];
        $at = array_search($this->screen, $order, true);
        $this->show($order[$at === false ? 0 : max($at - 1, 0)], 'back');
    }

    /** A step of the step bar clicked: one reached before (going forward, the open step is checked first). */
    public function jump(string $screen): void
    {
        $order = [...self::STEPS, self::REVIEW];
        $to = array_search($screen, $order, true);
        $at = array_search($this->screen, $order, true);
        if ($to === false || $to > $this->reached || $to === $at) {
            return;
        }
        if ($to > $at && ! $this->checkStep($this->screen)) {
            return;
        }

        $this->resetErrorBag();
        $this->show($screen, $to < $at ? 'back' : 'forward');
    }

    /** 変更 of a row of the check: its step (次へ there comes back to the check). */
    public function goTo(string $screen): void
    {
        if (in_array($screen, self::STEPS, true)) {
            $this->resetErrorBag();
            $this->show($screen, 'back');
            $this->fromReview = true;
        }
    }

    public function toggleCategory(int $id): void
    {
        if (array_key_exists($id, $this->categoryOptions())) {
            $this->categories = self::toggled($this->categories, $id);
        }
    }

    /** An employee tapped; one who is a member through a chosen 従業員カテゴリ stays. */
    public function toggleUser(int $id): void
    {
        if (array_key_exists($id, $this->employees()) && ! array_key_exists($id, $this->coveredUsers())) {
            $this->users = self::toggled($this->users, $id);
            $this->resetErrorBag('users');
        }
    }

    public function toggleApp(int $id): void
    {
        if (array_key_exists($id, $this->appOptions())) {
            $this->apps = self::toggled($this->apps, $id);
        }
    }

    /** 表示中をすべて選択 / 表示中の選択を解除 of メンバー (the employees the search shows) or アプリ. */
    public function selectListed(string $what, bool $on): void
    {
        if ($what === 'users') {
            $ids = array_keys(array_diff_key($this->listedEmployees(), $this->coveredUsers()));
            $this->users = $on ? self::merged($this->users, $ids) : array_values(array_diff($this->users, $ids));
        } elseif ($what === 'apps') {
            $ids = array_keys($this->listedApps());
            $this->apps = $on ? self::merged($this->apps, $ids) : array_values(array_diff($this->apps, $ids));
        }
    }

    /** 保存 on the check: every step is checked (the first wrong one is shown), then SpaceService saves. */
    public function save(): void
    {
        abort_unless(self::allowed(), 403);

        foreach (self::STEPS as $step) {
            if (! $this->checkStep($step)) {
                $this->show($step, 'back');
                $this->fromReview = true;

                return;
            }
        }

        $space = $this->spaceId !== null ? $this->space($this->spaceId) : null;
        if ($this->spaceId !== null && $space === null) {
            $this->close();

            return;
        }

        try {
            $saved = app(SpaceService::class)->save($space, $this->panel, [
                'name' => trim($this->name),
                'user_ids' => $this->users,
                'category_ids' => $this->categories,
                'app_ids' => $this->apps,
            ], DashboardSettingsPlugin::actor() ?? throw new AppActionNotAllowed('ログインが必要です。'));
        } catch (AppActionNotAllowed $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($space === null ? 'スペースを作成しました' : 'スペースを保存しました')->send();
        $this->open = false;
        $this->dispatch(self::SAVED_EVENT, space: $saved->id);
    }

    public function heading(): string
    {
        return $this->spaceId === null ? 'スペースを作成' : 'スペースを編集';
    }

    /**
     * The step bar: every step with its label, whether it is open, done (before the open one) or can be clicked.
     *
     * @return list<array{key: string, label: string, current: bool, done: bool, enabled: bool}>
     */
    public function stepBar(): array
    {
        $labels = ['name' => 'スペース名', 'members' => 'メンバー', 'apps' => 'アプリ', self::REVIEW => '確認'];
        $order = [...self::STEPS, self::REVIEW];
        $at = (int) array_search($this->screen, $order, true);

        return array_map(fn (string $key, int $index): array => [
            'key' => $key,
            'label' => $labels[$key],
            'current' => $index === $at,
            'done' => $index < $at,
            'enabled' => $index !== $at && $index <= $this->reached,
        ], $order, array_keys($order));
    }

    /**
     * The rows of the check.
     *
     * @return list<array{label: string, value: string, screen: string, invalid: bool}>
     */
    public function summary(): array
    {
        $categories = array_intersect_key($this->categoryOptions(), array_flip($this->categories));
        $people = array_intersect_key($this->employees(), array_flip($this->users));
        $apps = array_intersect_key($this->appOptions(), array_flip($this->apps));
        $members = count($this->memberIds());

        return [
            ['label' => 'スペース名', 'value' => trim($this->name), 'screen' => 'name', 'invalid' => $this->getErrorBag()->has('name')],
            ['label' => 'メンバー', 'value' => $members === 0 ? '' : $members.'名：'.self::listed([
                ...array_map(fn (array $category): string => $category['name'], $categories),
                ...array_map(fn (array $person): string => $person['name'], $people),
            ], $categories === [] ? '名' : '件'), 'screen' => 'members', 'invalid' => $this->getErrorBag()->has('users')],
            ['label' => 'アプリ', 'value' => $apps === [] ? '' : count($apps).'件：'.self::listed(array_map(fn (array $app): string => $app['name'], $apps)), 'screen' => 'apps', 'invalid' => false],
        ];
    }

    /**
     * The 従業員カテゴリ that can be chosen (active, and those the space has), with their number of employees.
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function categoryOptions(): array
    {
        return once(fn (): array => EmployeeCategory::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $this->categories))
            ->withCount(['users' => fn ($query) => $query->where('user_type', UserType::Employee->value)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (EmployeeCategory $category): array => [$category->id => ['name' => $category->name, 'count' => (int) $category->getAttribute('users_count')]])
            ->all());
    }

    /**
     * Every employee, with the ids and names of their 従業員カテゴリ.
     *
     * @return array<int, array{name: string, categories: array<int, string>}>
     */
    public function employees(): array
    {
        return once(fn (): array => User::query()
            ->with('employeeCategories:id,name')
            ->where('user_type', UserType::Employee->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => [
                'name' => $user->name,
                'categories' => $user->employeeCategories->pluck('name', 'id')->all(),
            ]])
            ->all());
    }

    /**
     * The employees who are members through a chosen 従業員カテゴリ, with the names of those categories.
     *
     * @return array<int, list<string>>
     */
    public function coveredUsers(): array
    {
        $chosen = array_flip($this->categories);
        $covered = [];
        foreach ($this->employees() as $id => $person) {
            $through = array_values(array_intersect_key($person['categories'], $chosen));
            if ($through !== []) {
                $covered[$id] = $through;
            }
        }

        return $covered;
    }

    /** @return list<int> everyone who will be a member */
    public function memberIds(): array
    {
        return array_values(array_unique([...array_keys($this->coveredUsers()), ...array_intersect($this->users, array_keys($this->employees()))]));
    }

    /** @return array<int, array{name: string, count: int}> the 従業員カテゴリ the search shows */
    public function listedCategories(): array
    {
        return array_filter($this->categoryOptions(), fn (array $category): bool => $this->matches($category['name']));
    }

    /** @return array<int, array{name: string, categories: array<int, string>}> the employees the search shows (by name or 従業員カテゴリ) */
    public function listedEmployees(): array
    {
        return array_filter($this->employees(), fn (array $person): bool => $this->matches($person['name'].' '.implode(' ', $person['categories'])));
    }

    /**
     * Every app (an app may be in several spaces), in the order of the registry.
     *
     * @return array<int, array{name: string, icon: string}>
     */
    public function appOptions(): array
    {
        return once(fn (): array => BusinessApp::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (BusinessApp $app): array => [$app->id => ['name' => $app->name, 'icon' => $app->iconText()]])
            ->all());
    }

    /** @return array<int, array{name: string, icon: string}> the apps the search shows */
    public function listedApps(): array
    {
        return array_filter($this->appOptions(), fn (array $app): bool => $this->matches($app['name']));
    }

    public function render(): View
    {
        return view('core::filament.space-wizard');
    }

    private function load(int $id): void
    {
        $space = $this->space($id);
        if ($space === null) {
            return;
        }

        $this->spaceId = $space->id;
        $this->name = $space->name;
        $this->users = $space->users()->wherePivot('direct', true)->pluck('users.id')->map(fn (mixed $user): int => (int) $user)->all();
        $this->categories = $space->employeeCategories()->pluck('employee_categories.id')->map(fn (mixed $category): int => (int) $category)->all();
        $this->apps = $space->apps()->pluck('business_apps.id')->map(fn (mixed $app): int => (int) $app)->all();
        $this->screen = self::REVIEW;
        $this->reached = count(self::STEPS);
        $this->fromReview = false;
        $this->open = true;
    }

    /** A space of this dashboard. */
    private function space(int $id): ?Space
    {
        return Space::query()->where('panel_id', $this->panel)->find($id);
    }

    /** The values of a step are checked; false (with the errors) when they are wrong. */
    private function checkStep(string $step): bool
    {
        $this->resetErrorBag();
        $rules = match ($step) {
            'name' => ['name' => ['required', 'string', 'max:64', Rule::unique('spaces', 'name')->where('panel_id', $this->panel)->ignore($this->spaceId)]],
            'members' => ['users' => ['array', function (string $attribute, mixed $value, Closure $fail): void {
                $ids = array_values(array_unique(array_map('intval', (array) $value)));
                if (count(array_intersect($ids, array_keys($this->employees()))) !== count($ids)) {
                    $fail('従業員以外のユーザーは選択できません。');
                }
            }]],
            default => [],
        };

        $validator = Validator::make(['name' => trim($this->name), 'users' => $this->users], $rules, [], ['name' => 'スペース名', 'users' => 'メンバー']);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return false;
        }

        return true;
    }

    private function show(string $screen, string $direction): void
    {
        $this->reached = max($this->reached, (int) array_search($screen, [...self::STEPS, self::REVIEW], true));
        $this->screen = $screen;
        $this->direction = $direction;
        $this->search = '';
        $this->fromReview = false;
    }

    private function matches(string $text): bool
    {
        $text = SearchText::normalize($text);
        foreach (SearchText::words(SearchText::normalize($this->search)) as $word) {
            if (! str_contains($text, $word)) {
                return false;
            }
        }

        return true;
    }

    private static function allowed(): bool
    {
        return DashboardSettingsPlugin::actorIsAdministrator();
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function toggled(array $ids, int $id): array
    {
        return in_array($id, $ids, true) ? array_values(array_diff($ids, [$id])) : [...$ids, $id];
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $more
     * @return list<int>
     */
    private static function merged(array $ids, array $more): array
    {
        return array_values(array_unique([...$ids, ...$more]));
    }

    /** @param  array<array-key, string>  $names  the first five, then 「ほかN件」 (people: 「ほかN名」) */
    private static function listed(array $names, string $unit = '件'): string
    {
        $names = array_values($names);
        $shown = implode('、', array_slice($names, 0, 5));

        return count($names) > 5 ? $shown.' ほか'.(count($names) - 5).$unit : $shown;
    }
}
