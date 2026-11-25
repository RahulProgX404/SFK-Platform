<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 表示件数 (each user's choice, every app) and the page numbers under an app list.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait ListPagination
{
    /** 表示件数 choices; blank = DEFAULT_PAGE_SIZE */
    public const PAGE_SIZES = [20, 40, 60, 80, 100, 200, 300];

    /** 60 (user 2026-11-18; was 100): a 100-row list took ~2.5× the server and browser time of a short one (docs/performance.md) */
    public const DEFAULT_PAGE_SIZE = 60;

    /** 表示件数 of the signed-in user. */
    public function pageSize(): int
    {
        $user = auth()->user();
        $size = $user instanceof User ? (int) $user->list_page_size : 0;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    public function setPageSize(int $size): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! in_array($size, self::PAGE_SIZES, true)) {
            return;
        }

        $user->forceFill(['list_page_size' => $size])->save();
        $this->tableRecordsPerPage = $size;
        $this->resetTable();
    }

    /** @return array{from: int, to: int, total: int, previous: bool, next: bool, current: int, pages: list<int|null>}|null pages: null = 「…」 */
    public function appPager(): ?array
    {
        if (! $this->currentView()->isTable()) {
            return null;
        }

        $records = $this->getTableRecords();

        if (! $records instanceof LengthAwarePaginator || $records->total() === 0) {
            return null;
        }

        return [
            'from' => (int) $records->firstItem(),
            'to' => (int) $records->lastItem(),
            'total' => $records->total(),
            'previous' => $records->currentPage() > 1,
            'next' => $records->hasMorePages(),
            'current' => $records->currentPage(),
            'pages' => self::pageNumbers($records->currentPage(), $records->lastPage()),
        ];
    }

    /**
     * The page buttons: the first and the last page and two on each side of the current one; null (「…」) for a gap.
     *
     * @return list<int|null>
     */
    public static function pageNumbers(int $current, int $last): array
    {
        $pages = [];
        foreach (range(1, max(1, $last)) as $page) {
            if ($page === 1 || $page === $last || abs($page - $current) <= 2) {
                $pages[] = $page;
            } elseif (end($pages) !== null) {
                $pages[] = null;
            }
        }

        return $pages;
    }

    public function gotoAppPage(int $page): void
    {
        $this->gotoPage(max(1, $page), $this->getTablePaginationPageName());
    }

    public function previousAppPage(): void
    {
        $this->previousPage($this->getTablePaginationPageName());
    }

    public function nextAppPage(): void
    {
        $this->nextPage($this->getTablePaginationPageName());
    }
}
