<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Application\Partners\PartnerCsvExport;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Filament\Resources\Partners\Tables\PartnersTable;
use Modules\MasterData\Models\Partner;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListPartners extends AppListRecords
{
    protected static string $resource = PartnerResource::class;

    public function listColumns(): array
    {
        return PartnersTable::columns();
    }

    /** The legacy view 取引先一覧 (SYSTEM_BRAIN APP_01 views.md): its columns, sorted by 取引先コード. */
    public function defaultListViews(): array
    {
        return [
            ListView::table(PartnersTable::VIEW_LIST, '取引先一覧', PartnersTable::LIST_COLUMNS, [['field' => 'code', 'direction' => 'asc']]),
        ];
    }

    public function filterFields(): array
    {
        return PartnersTable::filterFields();
    }

    protected function optionActions(): array
    {
        return [
            Action::make('export')
                ->label('ファイルに書き出す')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => PartnerResource::appCan(AppAbility::Export))
                ->action(fn (): StreamedResponse => app(PartnerCsvExport::class)->download($this->exportQuery())),
        ];
    }

    /** @return Builder<Partner> */
    private function exportQuery(): Builder
    {
        $query = $this->getFilteredSortedTableQuery();

        return $query instanceof Builder ? $query : Partner::query();
    }
}
