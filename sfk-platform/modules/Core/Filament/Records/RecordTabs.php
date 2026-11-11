<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabs and summary cards one module adds to the record page of another module's app (e.g. 受注 / 請求書 / 売掛明細 of
 * the Orders and Sales modules on a 01. 取引先マスタ record, design_ref/customer-*.png), where the owning module
 * cannot depend on them. A module registers them in its service provider for a page key (e.g. 'master-data.partners');
 * the page asks for those that apply to its record. The data is read through the providing app's
 * `Resource::onlyVisible()`, so app and record rights apply. The summary cards — of the record and of its tabs — are the
 * company's money: only users who see everyone's money get them (User::seesOverallFinance(), as the summary cards of the
 * app lists; user 2026-11-11).
 */
final class RecordTabs
{
    /**
     * @var array<string, list<array{key: string, label: string, icon: string, sort: int, when: Closure(Model): bool, data: Closure(Model): array<string, mixed>}>>
     */
    private array $tabs = [];

    /** @var array<string, list<array{sort: int, cards: Closure(Model): list<array<string, string>>}>> */
    private array $cards = [];

    /**
     * A tab: data() returns columns (key => label), numeric (keys aligned right), rows (key => text), urls (record link
     * per row, or null), optionally cards (summary cards of the tab) and tones (key => [value => tone] for badges).
     *
     * @param  Closure(Model): bool  $when  whether the record has the tab (e.g. only for 得意先, only with app rights)
     * @param  Closure(Model): array<string, mixed>  $data
     */
    public function tab(string $page, string $key, string $label, string $icon, int $sort, Closure $when, Closure $data): void
    {
        $this->tabs[$page][] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'sort' => $sort, 'when' => $when, 'data' => $data];
    }

    /**
     * Summary cards of the record (label, value, tone, icon — see AppListRecords::summaryCard()).
     *
     * @param  Closure(Model): list<array<string, string>>  $cards
     */
    public function cards(string $page, int $sort, Closure $cards): void
    {
        $this->cards[$page][] = ['sort' => $sort, 'cards' => $cards];
    }

    /** @return list<array{key: string, label: string, icon: string}> the tabs of the record, in order */
    public function tabsFor(string $page, Model $record): array
    {
        $tabs = array_values(array_filter($this->tabs[$page] ?? [], fn (array $tab): bool => ($tab['when'])($record)));
        usort($tabs, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(fn (array $tab): array => ['key' => $tab['key'], 'label' => $tab['label'], 'icon' => $tab['icon']], $tabs);
    }

    /**
     * @return array{columns: array<string, string>, numeric: list<string>, rows: list<array<string, string>>, urls: list<string|null>, cards: list<array<string, string>>, tones: array<string, array<string, string>>}|null
     */
    public function data(string $page, string $key, Model $record): ?array
    {
        foreach ($this->tabs[$page] ?? [] as $tab) {
            if ($tab['key'] === $key && ($tab['when'])($record)) {
                $data = ['columns' => [], 'numeric' => [], 'rows' => [], 'urls' => [], 'cards' => [], 'tones' => [], ...($tab['data'])($record)];

                return self::showsCards() ? $data : [...$data, 'cards' => []];
            }
        }

        return null;
    }

    /** @return list<array<string, string>> */
    public function cardsFor(string $page, Model $record): array
    {
        if (! self::showsCards()) {
            return [];
        }

        $providers = $this->cards[$page] ?? [];
        usort($providers, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_merge(...array_map(fn (array $provider): array => ($provider['cards'])($record), $providers ?: [['cards' => fn (): array => []]]));
    }

    /** The summary cards are for users who see everyone's money (財務分析を表示 + 範囲 全体; super admins always). */
    private static function showsCards(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->seesOverallFinance();
    }
}
