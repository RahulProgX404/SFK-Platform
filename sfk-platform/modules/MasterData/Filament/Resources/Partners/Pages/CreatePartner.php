<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppPages\AppCreateRecord;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

final class CreatePartner extends AppCreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected static ?string $title = '取引先を追加';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $roles = (array) ($data['roles'] ?? []);
        unset($data['roles']);

        return [...$data, ...Partner::roleColumns($roles)];
    }

    /**
     * レコードを再利用する: 取引先コード is numbered anew; legacy / external ids and attachments are not copied.
     *
     * @return list<string>
     */
    protected function duplicateExcludedFields(): array
    {
        return ['code', 'legacy_id', 'mf_partner_id', 'contract_files', 'supplier_contract_files', 'is_customer', 'is_supplier', 'is_other'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateDuplicateData(array $data, Model $source): array
    {
        if ($source instanceof Partner) {
            $data['roles'] = array_map(fn (PartnerRole $role): string => $role->value, $source->roles());
            $data['staff'] = $source->staff()->pluck('users.id')->all();
        }

        return $data;
    }

    /** Like the legacy system, show the saved record. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
