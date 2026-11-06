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
use Modules\Sales\Filament\Resources\Sales\SaleResource;

/**
 * A card of the dashboard (/erp, Modules\Core\Filament\Portal\PortalPage; design_ref/Dashboard.png): a widget of the
 * erp panel drawn in the dashboard's grid (its view's root says `sfk-dash-span-<n>` of 12 columns; core::filament.portal) for the period of
 * the header's date button (page filters `from` / `to`). Rows 1–2 are the money (財務分析を表示), rows 3–4 for everyone.
 * The money is everyone's or, 個人 (page filter `scope`, or 財務分析の範囲 個人のみ), only the user's own sales.
 */
abstract class DashboardCard extends Widget
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
