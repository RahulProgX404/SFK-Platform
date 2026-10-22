<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\Core\Filament\Records\RecordSections;
use Modules\MasterData\Domain\Partners\PartnerFields;

/**
 * The form of 01. 取引先マスタ (fields of SYSTEM_BRAIN APP_01 schema.json), grouped on a 12-column grid of the 1,500 px
 * canvas. Above the tabs: 基本情報 (コード, 取引先名, ふりがな; 略名, 取引先カテゴリ, 登録番号), 住所・連絡先, 親会社/グループ会社
 * and 契約書等添付. 得意先 tab (基本情報): 得意先区分, 担当, 請求・回収条件 and その他; 仕入先情報 tab: 仕入条件, 住所・連絡先
 * (with 仕入先住所も同様) and その他. The form and the record page both build from it. 取引先名(ふりがな) is new (not in the legacy app). The
 * その他関連情報 tab of the old JS pointed to a missing group (D-01-1) and is not rebuilt.
 */
final class PartnerLayout
{
    /**
     * Tabs above the record; 見積履歴 / 納品履歴 / 請求履歴 list the partner's documents once apps 09/10/11 exist and stay
     * empty (no message) while there are none.
     */
    private const TABS = [
        'basic' => '基本情報',
        'supplier' => '仕入先情報',
        'quotes' => '見積履歴',
        'deliveries' => '納品履歴',
        'invoices' => '請求履歴',
    ];

    /** Rows shown on every tab: [attribute|null (spacer), width px]. */
    private const HEAD = [
        '基本情報',
        [['code', 250], ['name', 625], ['name_kana', 625]],
        [['short_name', 375], ['roles', 750], ['registration_number', 375]],
        '住所・連絡先',
        [['postal_code', 250], ['address', 750], ['phone', 250], ['fax', 250]],
        '親会社/グループ会社',
        [['parent_company', 750]],
        [['parent_postal_code', 250], ['parent_address', 750], ['parent_phone', 250], ['parent_fax', 250]],
        '契約書等添付',
        [['contract_files', 750], ['supplier_contract_files', 750]],
    ];

    private const BASIC = [
        '得意先区分',
        [['customer_status', 375], ['customer_classification', 375], ['partner_group', 375], ['segment', 375]],
        '担当',
        [['staff', 750], ['contact_person', 750]],
        [['contact_note', 1500]],
        '請求・回収条件',
        [['closing_day', 250], ['collection_month', 250], ['collection_day', 250], ['collects_by_bill', 250], ['bill_threshold_man', 250], ['bill_site_days', 250]],
        [['tax_mode', 250], ['rounding', 250], ['tax_calculation_unit', 250], ['electronic_billing', 250]],
        [['invoice_recipient', 375], ['delivery_note_recipient', 375], ['invoice_issuance', 375], ['delivery_note_issuance', 375]],
        'その他',
        [['notes', 1500]],
        [['legacy_customer_code', 250]],
    ];

    private const SUPPLIER = [
        '仕入条件',
        [['purchase_category', 375], ['supplier_tax_mode', 375], ['supplier_rounding', 375], ['supplier_tax_calculation_unit', 375]],
        '住所・連絡先',
        [['supplier_contact_person', 375], ['supplier_same_address', 375]],
        [['supplier_postal_code', 250], ['supplier_address', 750], ['supplier_phone', 250], ['supplier_fax', 250]],
        'その他',
        [['supplier_notes', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 196], ['mf_partner_id', 196], ['legacy_supplier_code', 196]]];

    /**
     * @param  callable(string): Component  $make  builds the form input or record entry of an attribute
     * @param  bool  $withFooter  得意先ID / 作成者 / 更新者 row (record page only)
     * @param  bool  $withDeveloperFields  開発者用フィールド (super admins only)
     * @return list<Component>
     */
    public static function components(callable $make, bool $withFooter, bool $withDeveloperFields): array
    {
        $components = [
            Section::make()->schema(self::rows(self::HEAD, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull(),
            RecordLayout::tabPanel('basic', self::group(self::rows(self::BASIC, $make)), selected: true),
            RecordLayout::tabPanel('supplier', self::group(self::rows(self::SUPPLIER, $make))),
            // related records: an empty tab while there are none (no message)
            ...array_map(
                fn (string $tab): Component => RecordLayout::tabPanel($tab, self::group([])),
                ['quotes', 'deliveries', 'invoices'],
            ),
        ];

        if ($withFooter) {
            $components[] = Section::make()->schema(self::rows(self::FOOTER, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
        }

        if ($withDeveloperFields) {
            $components[] = Section::make('開発者用フィールド')
                ->collapsed()
                ->schema(self::rows(self::DEVELOPER, $make))
                ->extraAttributes(['class' => 'kt-record'])
                ->columnSpanFull();
        }

        return [RecordLayout::tabbed(self::TABS, $components)];
    }

    public static function label(string $attribute): string
    {
        return PartnerFields::label($attribute);
    }

    /** @param  list<Component>  $schema */
    private static function group(array $schema): Section
    {
        return Section::make()->schema($schema)->extraAttributes(['class' => 'kt-record kt-record-group'])->columnSpanFull();
    }

    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows  rows, a string row is a group label
     * @param  callable(string): Component  $make
     * @return list<Component>
     */
    private static function rows(array $rows, callable $make): array
    {
        return RecordSections::rows($rows, $make);
    }
}
