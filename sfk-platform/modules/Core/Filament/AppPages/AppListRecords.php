<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;

/**
 * Record list of an app: app frame, the app's list views as a dropdown (getListViews(); columns read `listView`),
 * ＋ / gear / 「…」 (header actions, e.g. ファイルに書き出す) and 「1 - 100（N件中）」 paging above and below the list.
 */
abstract class AppListRecords extends ListRecords
{
    use HasAppChrome;

    public const PER_PAGE = 100;

    #[Url(as: 'view')]
    public string $listView = '';

    /**
     * The app's list views, key => name; the first one is the default.
     *
     * @return array<string, string>
     */
    public function getListViews(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();

        if (! array_key_exists($this->listView, $this->getListViews())) {
            $this->listView = (string) array_key_first($this->getListViews());
        }
    }

    public function updatedListView(): void
    {
        $this->resetPage();
    }

    public function appPageKind(): string
    {
        return 'list';
    }

    public function getTitle(): string
    {
        return $this->appResource()::appName();
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated([self::PER_PAGE])
            ->defaultPaginationPageOption(self::PER_PAGE)
            ->striped();
    }

    /** @return array{from: int, to: int, total: int, previous: bool, next: bool}|null */
    public function appPager(): ?array
    {
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
        ];
    }

    public function previousAppPage(): void
    {
        $this->previousPage($this->getTablePaginationPageName());
    }

    public function nextAppPage(): void
    {
        $this->nextPage($this->getTablePaginationPageName());
    }

    public function getFooter(): View
    {
        return view('core::filament.app-pager', ['page' => $this, 'position' => 'bottom']);
    }
}
