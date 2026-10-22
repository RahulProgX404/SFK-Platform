<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Sales;

use Filament\Schemas\Components\Component;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Sales\Domain\Sales\SaleFields;

/**
 * The form of 06. 売上入力 (fields of SYSTEM_BRAIN APP_06 fields.md), grouped by task with the most used first, on a
 * 12-column grid of the 1,500 px canvas (125 px a column): 基本情報 (区分, 売上No, 売上日, 納品予定日; 売上先, 注番 and
 * 担当者 — 注番 copies 品名 / 担当者 / 見積原価 of the job; 品名 and 摘要), 金額 (数量 → 単位 → 単価 → 金額 → 税率 → 消費税 →
 * 税込), 注番の状況 (the job's figures, 按分 and 同注番の売上履歴), 請求・送付先 and 手配・状態 (即時依頼等, 納品書 / 請求書).
 * 納品回数 / 請求回数 are left out (unreliable legacy counters, D-06). The form and the record page both build from it;
 * `$make` returns null for what a screen does not show.
 */
final class SaleLayout
{
    private const MAIN = [
        '基本情報',
        [['kind', 750], ['sales_number', 250], ['sold_on', 250], ['delivery_due_on', 250]],
        [['customer_id', 750], ['order_id', 375], ['assignees', 375]],
        [['product_name', 750], ['summary', 750]],
        '金額',
        [['quantity', 250], ['unit', 125], ['unit_price', 250], ['amount', 250], ['tax_rate', 125], ['tax', 250], ['amount_with_tax', 250]],
        '注番の状況',
        [['order_customer', 500], ['is_fsc', 125], ['order_quantity', 250], ['order_sold_quantity', 250], ['order_remaining', 375]],
        [['order_sales_amount', 375], ['order_sold_amount', 375], ['estimated_cost', 375], ['estimated_expense', 375]],
        [['allocations', 750]],
        [['same_order_sales', 1500]],
        '請求・送付先',
        [['closing_day', 250], ['closing_day_override', 250], ['billing_date', 250], ['delivery_note_recipient', 375], ['invoice_recipient', 375]],
        [['other_address', 375], ['manual_postal_code', 250], ['manual_address', 875]],
        [['customer_postal_code', 250], ['customer_address', 500], ['parent_postal_code', 250], ['parent_address', 500]],
        '手配・状態',
        [['needs', 500], ['handover', 1000]],
        [['delivered', 125], ['delivery_note_id', 250], ['invoiced', 125], ['invoice_id', 250], ['locked', 250]],
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
