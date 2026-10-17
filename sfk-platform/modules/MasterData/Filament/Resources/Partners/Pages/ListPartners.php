<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Apps\AppAbility;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\MasterData\Application\Partners\PartnerCsvExport;
use Modules\MasterData\Domain\Partners\PartnerRole;
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

    public function inlineFields(): array
    {
        return PartnersTable::inlineFields();
    }

    /** 取引先カテゴリ from the role flags, 自社担当者 as user ids. */
    protected function inlineEditState(Model $record): array
    {
        $state = parent::inlineEditState($record);

        if ($record instanceof Partner) {
            $state['roles'] = array_map(fn (PartnerRole $role): string => $role->value, $record->roles());
            $state['staff'] = $record->staff()->pluck('users.id')->map(fn (mixed $id): string => (string) $id)->all();
        }

        return $state;
    }

    /** 取引先カテゴリ is stored as the role flags (BR-MD-02), 自社担当者 in partner_staff. */
    protected function saveInlineRecord(Model $record, array $data): void
    {
        $roles = (array) ($data['roles'] ?? []);
        $staff = array_map('intval', (array) ($data['staff'] ?? []));
        unset($data['roles'], $data['staff']);

        $record->fill([...$data, ...Partner::roleColumns($roles)])->save();

        if ($record instanceof Partner) {
            $record->staff()->sync($staff);
        }
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
