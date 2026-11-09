<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Dashboard;

use App\Models\User;
use DateTimeImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Modules\Analytics\Application\SalesDashboard;
use Modules\Analytics\Domain\Period;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\Portal\FollowsDashboardFilters;
use Modules\Sales\Filament\Resources\Sales\SaleResource;

/**
 * A card of the dashboard (/erp, Modules\Core\Filament\Portal\PortalPage; design_ref/Dashboard.png): a widget of the
 * erp panel drawn in the dashboard's grid (its view's root says `sfk-dash-span-<n>` of 12 columns; core::filament.portal) for the period of
 * the header's date button (page filters `from` / `to`). Rows 1–2 are the money (財務分析を表示), rows 3–4 for everyone.
 * The money is everyone's or, 個人 (page filter `scope`, or 財務分析の範囲 個人のみ), only the user's own sales.
 *
 * 個人 has its own layout (bug.md 2026-11-09), the same for 個人のみ and for 全体 switched to 個人 — only the figures are
 * the user's: 売上合計 · 売上概要 (the rest of the row); 売上の推移 (about 70%) · 最近表示した項目 (クイックアクション);
 * 休暇・勤怠予定 · 最近のアクティビティ (half each). A card says whether it is in it (shownInLayout()) and where
 * (sortInLayout()); PortalPage asks through FollowsDashboardFilters.
 */
abstract class DashboardCard extends Widget implements FollowsDashboardFilters
{
    use InteractsWithPageFilters;

    /** drawn with the page: the cards of a dashboard are few and quick */
    protected static bool $isLazy = false;

    protected function period(): Period
    {
        return Period::between($this->pageFilters['from'] ?? null, $this->pageFilters['to'] ?? null, $this->today());
    }

    /**
     * 個人: the user whose own sales the money cards show — always for 財務分析の範囲 個人のみ, for 全体 when the header's
     * 全体 | 個人 says 個人; null = everyone's.
     */
    protected function staffId(): ?int
    {
        $user = static::actor();
        if ($user === null) {
            return null;
        }

        return ! $user->seesOverallFinance() || ($this->pageFilters['scope'] ?? null) === User::FINANCE_PERSONAL ? $user->id : null;
    }

    /** @param  array<string, mixed>  $filters */
    public static function shownOnDashboard(array $filters): bool
    {
        return static::shownInLayout(static::personalLayout($filters));
    }

    /** @param  array<string, mixed>  $filters */
    public static function sortOnDashboard(array $filters): int
    {
        return static::sortInLayout(static::personalLayout($filters));
    }

    /**
     * The 個人 layout of the dashboard: a user with 財務分析を表示 who sees only their own money — 個人のみ, or 全体 with
     * 個人 chosen in the header. Users without 財務分析を表示 keep theirs (個人の概要 first).
     *
     * @param  array<string, mixed>|null  $filters  the page filters
     */
    public static function personalLayout(?array $filters): bool
    {
        $user = static::actor();

        return $user !== null && static::showsMoney()
            && (! $user->seesOverallFinance() || ($filters['scope'] ?? null) === User::FINANCE_PERSONAL);
    }

    /** whether the card is in the dashboard's layout (canView() aside): every layout unless it says otherwise */
    protected static function shownInLayout(bool $personal): bool
    {
        return true;
    }

    /** its place in the dashboard's layout */
    protected static function sortInLayout(bool $personal): int
    {
        return static::getSort();
    }

    /** this card is drawn in the 個人 layout */
    protected function inPersonalLayout(): bool
    {
        return static::personalLayout($this->pageFilters);
    }

    /** The money of the dashboard for the chosen scope (everyone's or the user's own). */
    protected function salesData(): SalesDashboard
    {
        return app(SalesDashboard::class)->forStaff($this->staffId());
    }

    protected function today(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable()->setTime(0, 0);
    }

    protected static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** 財務分析を表示 and the sales the money comes from. */
    protected static function showsMoney(): bool
    {
        return (static::actor()?->seesFinancialAnalysis() ?? false) && SaleResource::canViewAny();
    }

    /**
     * 財務分析の範囲 全体 (bug.md 2026-11-07): 作業者ランキング, 上位の売上先, 担当者別売上 and 売れ筋 TOP3 are only for
     * users who see everyone's money (super admins always).
     */
    protected static function seesEveryonesMoney(): bool
    {
        return static::actor()?->seesOverallFinance() ?? false;
    }

    /**
     * Width of 休暇・勤怠予定 and クイックアクション in row 3: a third next to 作業者ランキング (全体), else half each so the
     * row stays full.
     */
    protected function teamCardSpan(): int
    {
        return static::seesEveryonesMoney() && ! $this->inPersonalLayout() ? 4 : 6;
    }

    /** The list of an app in this dashboard, while the user may open it. */
    protected static function appUrl(string $appCode): ?string
    {
        $resource = AppResource::forCode($appCode);

        return $resource !== null && $resource::canViewAny() ? AppResource::urlForApp($appCode, Filament::getCurrentOrDefaultPanel()) : null;
    }

    /** 2 characters of a name for its avatar (株式会社 and the like left out). */
    public static function initials(string $name): string
    {
        $name = trim((string) preg_replace('/(株式会社|有限会社|合同会社|一般社団法人|医療法人社団|医療法人|（株）|\(株\)|㈱|㈲)/u', '', $name));

        return mb_substr($name !== '' ? $name : '?', 0, 2);
    }

    /** Percentage change, 1 decimal; null without a base. */
    public static function change(int|float $now, int|float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
