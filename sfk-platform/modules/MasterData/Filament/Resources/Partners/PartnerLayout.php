<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\MasterData\Domain\Partners\PartnerFields;

/**
 * The legacy form of 01. 取引先マスタ, row by row with the field widths of the app template
 * (SYSTEM_BRAIN APP_01 schema.json; group rows from the template ZIP). The form and the record page both build
 * from it, so they always look like the legacy record. 取引先名(ふりがな) is new (not in the legacy app). The
 * その他関連情報 tab of the old JS pointed to a missing group (D-01-1) and is not rebuilt.
 */
final class PartnerLayout
{
    /** Tabs above the record; 見積履歴 / 納品履歴 / 請求履歴 list the partner's documents once apps 09/10/11 exist. */
    private const TABS = [
        'basic' => '基本情報',
        'supplier' => '仕入先情報',
        'quotes' => '見積履歴',
        'deliveries' => '納品履歴',
        'invoices' => '請求履歴',
    ];

    /** Rows shown on every tab: [attribute|null (spacer), width px]. */
    private const HEAD = [
        [['code', 128], ['parent_company', 400], ['name', 400], ['name_kana', 400]],
        [['short_name', 472], ['registration_number', 259]],
        'label',
        [['parent_postal_code', 193], ['parent_address', 591], ['parent_phone', 193], ['parent_fax', 193]],
        [['postal_code', 193], ['address', 591], ['phone', 193], ['fax', 193], ['supplier_same_address', 148]],
        [['roles', 380], ['contract_files', 207], ['supplier_contract_files', 207]],
    ];

    private const BASIC = [
        [['customer_status', 196], ['customer_classification', 196], ['legacy_customer_code', 128], [null, 145], ['partner_group', 236], ['segment', 116]],
        [['invoice_recipient', 236], ['delivery_note_recipient', 236]],
        [['contact_person', 193], ['staff', 344]],
        [['contact_note', 980]],
        [['tax_mode', 196], ['rounding', 196], ['tax_calculation_unit', 196], ['closing_day', 196], ['invoice_issuance', 196], ['delivery_note_issuance', 196], ['electronic_billing', 94]],
        [['collection_month', 196], ['collection_day', 196], ['collects_by_bill', 108], ['bill_threshold_man', 193], ['bill_site_days', 106]],
        [['notes', 980]],
    ];

    private const SUPPLIER = [
        [['purchase_category', 196], ['supplier_tax_mode', 196], ['supplier_rounding', 196], ['supplier_tax_calculation_unit', 196]],
        [['supplier_contact_person', 193]],
        [['supplier_postal_code', 193], ['supplier_address', 591], ['supplier_phone', 193], ['supplier_fax', 193]],
        [['supplier_notes', 980]],
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
            ...array_map(
                fn (string $tab): Component => RecordLayout::tabPanel($tab, self::group([
                    Text::make('関連するレコードはありません。')->extraAttributes(['class' => 'kt-record-empty'])->columnSpanFull(),
                ])),
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
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows
     * @param  callable(string): Component  $make
     * @return list<Component>
     */
    private static function rows(array $rows, callable $make): array
    {
        return array_map(function (array|string $row) use ($make): Component {
            if ($row === 'label') {
                return Text::make('住所/連絡先 その他')->extraAttributes(['class' => 'kt-record-label'])->columnSpanFull();
            }

            return RecordLayout::row(array_map(
                fn (array $cell): array => [$cell[0] === null ? null : $make($cell[0]), $cell[1]],
                $row,
            ));
        }, $rows);
    }
}
