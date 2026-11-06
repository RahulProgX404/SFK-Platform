<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Pages\Concerns;

use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Modules\Analytics\Filament\Pages\DailyReportAnalysis;
use Modules\Analytics\Filament\Pages\SalesAnalysis;

/**
 * The frame of the 分析 pages (売上分析, 日報分析), in the look of the app screens (core::filament.app-header): the
 * breadcrumb home › 分析 › page, and links to the other 分析 page the user may open. Full width, no outer spacing.
 */
trait HasAnalysisChrome
{
    public function getHeader(): View
    {
        $pages = array_filter([
            SalesAnalysis::class => SalesAnalysis::canAccess() ? SalesAnalysis::getUrl() : null,
            DailyReportAnalysis::class => DailyReportAnalysis::canAccess() ? DailyReportAnalysis::getUrl() : null,
        ]);

        return view('analytics::filament.analysis-header', [
            'title' => static::$title,
            'tabs' => array_map(fn (string $page, string $url): array => ['label' => $page::titleText(), 'url' => $url, 'active' => $page === static::class], array_keys($pages), $pages),
            'homeUrl' => Filament::getCurrentPanel()?->getUrl() ?? url('/'),
        ]);
    }

    /** The page's name (売上分析, 日報分析). */
    public static function titleText(): string
    {
        return (string) static::$title;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-analysis-page'];
    }
}
