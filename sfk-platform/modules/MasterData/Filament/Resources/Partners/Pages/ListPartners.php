<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
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

    /** The legacy views (SYSTEM_BRAIN APP_01 views.md). */
    public function getListViews(): array
    {
        return [
            PartnersTable::VIEW_LIST => '取引先一覧',
            PartnersTable::VIEW_ALL => '（すべて）',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('export')
                    ->label('ファイルに書き出す')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (): bool => PartnerResource::appCan(AppAbility::Export))
                    ->action(fn (): StreamedResponse => app(PartnerCsvExport::class)->download($this->exportQuery())),
            ])
                ->label('その他の操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->color('gray')
                ->iconButton(),
        ];
    }

    /** @return Builder<Partner> */
    private function exportQuery(): Builder
    {
        $query = $this->getFilteredSortedTableQuery();

        return $query instanceof Builder ? $query : Partner::query();
    }
}
