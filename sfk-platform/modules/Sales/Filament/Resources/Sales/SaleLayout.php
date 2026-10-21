<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales;

use Filament\Schemas\Components\Component;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Sales\Domain\Sales\SaleFields;

/**
 * The legacy form of 06. 売上入力 (template ZIP layout, SYSTEM_BRAIN APP_06 fields.md "Form layout"): 売上情報, 注番情報
 * with the table 按分 and 同注番の売上履歴, 即時依頼等, with the field widths of the template. The amounts row of the
 * legacy form is wider than the 1,500 px canvas and is split after 消費税. The 納品書 / 請求書 state of the legacy footer
 * row sits under 即時依頼等; 納品回数 / 請求回数 are left out (unreliable legacy counters, D-06). The form and the record
 * page both build from it; `$make` returns null for what a screen does not show.
 */
final class SaleLayout
{
    private const MAIN = [
        '売上情報',
        [['kind', 550], [null, 251], ['sold_on', 117], ['sales_number', 154]],
        [['customer_id', 550], ['closing_day', 193], ['closing_day_override', 196], ['billing_date', 117], ['delivery_due_on', 117]],
        [['delivery_note_recipient', 193], ['invoice_recipient', 193], ['other_address', 176]],
        [['manual_postal_code', 193], ['manual_address', 608]],
        [['parent_postal_code', 193], ['parent_address', 608]],
        [['customer_postal_code', 193], ['customer_address', 608]],
        [['quantity', 193], ['unit', 116], ['unit_price', 193], ['amount', 193], ['tax_rate', 125], ['amount_with_tax', 193], ['tax', 193]],
        [['estimated_cost', 193], ['estimated_expense', 193], ['summary', 341]],
        '注番情報',
        [['order_id', 309], ['order_customer', 436], ['product_name', 480]],
        [['assignees', 344], ['order_quantity', 135], ['order_sold_quantity', 135], ['order_remaining', 135], ['order_sales_amount', 193], ['is_fsc', 94], ['order_sold_amount', 193]],
        [['allocations', 626]],
        [['same_order_sales', 1500]],
        '即時依頼等',
        [['needs', 423], ['handover', 963]],
        [['delivered', 106], ['delivery_note_id', 193], ['invoiced', 106], ['invoice_id', 193], ['locked', 180]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 193], ['created_at', 196], ['updater', 193], ['updated_at', 196]]];

    private const DEVELOPER = [
        [['postal_code_p', 193], ['address_p', 772]],
        [['customer_code', 193], ['customer_rounding', 193], ['effective_closing_day', 193], ['billing_month', 193], ['sales_month', 193], ['legacy_id', 193]],
    ];

    /**
     * @param  callable(string): ?Component  $make  builds the form input or record entry of an attribute (null = not shown)
     * @param  bool  $withFooter  売上入力ID / 作成者 / 更新者 row (record page only)
     * @param  bool  $withDeveloperFields  開発者用フィールド (super admins only)
     * @return list<Component>
     */
    public static function components(callable $make, bool $withFooter, bool $withDeveloperFields): array
    {
        $components = [RecordSections::section(self::MAIN, $make)];

        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }

        if ($withDeveloperFields) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }

    public static function label(string $attribute): string
    {
        return SaleFields::label($attribute);
    }
}
