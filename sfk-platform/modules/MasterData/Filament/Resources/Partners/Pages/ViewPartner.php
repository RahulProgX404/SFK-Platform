<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Pages;

use App\Models\User;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Filament\AppPages\AppViewRecord;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\MasterData\Domain\Partners\PartnerFields;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Filament\Resources\Partners\PartnerResource;
use Modules\MasterData\Models\Partner;

/**
 * A 取引先 (design_ref/customer-overview.png … customer-statements.png): the partner's head — initial, 取引先コード, name,
 * 取引先カテゴリ, contact details and the summary cards — then the tabs: 概要 (the record with コメント | 変更履歴) and the
 * lists the other modules add (受注, 見積, 売上, 納品書, 請求書, 入金, 売掛明細, 仕入, 支払; Core RecordTabs page
 * 'master-data.partners'), each read through its app's rights.
 *
 * @property Partner $record
 */
final class ViewPartner extends AppViewRecord
{
    public const TABS = 'master-data.partners';

    protected static string $resource = PartnerResource::class;

    /** The tab shown: overview (概要) or a key of RecordTabs. */
    #[Url(as: 'tab')]
    public string $recordTab = 'overview';

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ViewComponent::make('core::filament.record-profile')
                ->viewData(fn (): array => $this->profile())
                ->hidden(fn (): bool => $this->printMode),
            Group::make($this->recordContentComponents())
                ->visible(fn (): bool => $this->recordTab === 'overview' || $this->printMode),
            ViewComponent::make('core::filament.record-tab')
                ->viewData(fn (): array => app(RecordTabs::class)->data(self::TABS, $this->recordTab, $this->record) ?? [])
                ->visible(fn (): bool => $this->recordTab !== 'overview' && ! $this->printMode),
        ]);
    }

    public function updatedRecordTab(): void
    {
        $keys = array_column($this->tabs(), 'key');
        if (! in_array($this->recordTab, $keys, true)) {
            $this->recordTab = 'overview';
        }
    }

    /** @return list<array{key: string, label: string, icon: string}> 概要 and the tabs of the other modules */
    private function tabs(): array
    {
        return [
            ['key' => 'overview', 'label' => '概要', 'icon' => 'heroicon-o-identification'],
            ...app(RecordTabs::class)->tabsFor(self::TABS, $this->record),
        ];
    }

    /** @return array<string, mixed> data of core::filament.record-profile */
    private function profile(): array
    {
        $partner = $this->record;
        // フィールドのアクセス権: a field the user may not see is left out of the head too
        $user = auth()->user();
        $app = PartnerResource::app();
        $value = fn (string $field): string => $user instanceof User && $app !== null && ! FieldAccess::level($user, $app, $field)->canView()
            ? ''
            : (string) $partner->getAttribute($field);
        $address = trim(implode(' ', array_filter([
            filled($value('postal_code')) ? '〒'.$value('postal_code') : null,
            $value('address'),
        ])));

        return [
            'initial' => mb_substr(filled($partner->short_name) ? $partner->short_name : $partner->name, 0, 1),
            'code' => $partner->code,
            'name' => $partner->name,
            'badges' => array_map(fn (PartnerRole $role): string => $role->value, $partner->roles()),
            'meta' => ($created = $partner->getAttribute('created_at')) instanceof \DateTimeInterface ? '登録日：'.$created->format('Y年n月j日') : null,
            'info' => [
                ['icon' => 'heroicon-o-phone', 'label' => 'TEL', 'value' => $value('phone')],
                ['icon' => 'heroicon-o-printer', 'label' => 'FAX', 'value' => $value('fax')],
                ['icon' => 'heroicon-o-map-pin', 'label' => '住所', 'value' => $address],
            ],
            'cards' => app(RecordTabs::class)->cardsFor(self::TABS, $partner),
            'tabs' => $this->tabs(),
            'active' => $this->recordTab,
        ];
    }

    /** @return array<string, string> 変更履歴 labels: the legacy field names */
    protected function historyLabels(): array
    {
        return PartnerFields::LABELS;
    }

    /** ‹ › follow the list order of 取引先一覧 (取引先コード). */
    protected function recordNavigationColumn(): string
    {
        return 'code';
    }
}
