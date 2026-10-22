<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Orders\Domain\Orders\OrderFields;

/**
 * The form of 04. 受注入力 (fields of SYSTEM_BRAIN APP_04 fields.md) on a 12-column grid of the 1,500 px canvas, section
 * by section with labels (orange mark): 基本情報 (得意先 / 注番 / ステータス, 品名, 数量, 受注日 / 納期 / 担当者, the production
 * progress), 売上・納品 (売上金額 / 原価 / 利益, 印刷日 / 持込日, 完成日), then the job in production order — 用　紙 / 版 下・
 * 製 版・刷 版 / 印　刷 / 製 袋・製 本・他 加 工 — and その他 (memos, 作業明細表, the costs with 日報一覧 / 仕入履歴), with the groups
 * 各種原価 and 開発者用フィールド. The form and the record page both build from it; `$make` returns null for what a screen does
 * not show (e.g. the related lists in the form), and such cells / rows are left out.
 *
 * The bag-making fields (仕入先(製造場所) … 送料) fold with 「▲ 折りたたむ」 / 「▼ 展開する」 (legacy toggle script, open
 * at first).
 */
final class OrderLayout
{
    private const HEAD = [
        'label:基本情報',
        [['customer_id', 750], ['order_number', 250], ['status', 250], ['closing_day', 250]],
        [['product_name', 625], ['is_fsc', 125], ['category1', 375], ['category2', 375]],
        [['quantity', 375], ['unit', 375], ['sample', 375], ['spare', 375]],
        [['order_date', 250], ['due_date', 250], ['due_time', 250], ['expected_hours', 250], ['assignees', 500]],
        [['manufactured_quantity', 375], ['manufacturing_remaining', 375], ['sold_quantity', 375], ['sales_remaining', 375]],
    ];

    private const PAPER = [
        [['papers', 1500]],
        [[null, 1250], ['paper_subtotal', 250]],
    ];

    private const PLATE = [
        'sub:刷版',
        [['plate_supplier_id', 500], ['plate_version', 250], ['plate_last_used_on', 250], ['plate_storage', 250], ['plate_size', 250]],
        [['plate_count', 250], ['plate_unit_price', 250], ['plate_amount', 250], ['design_fee', 250], ['plate_notes', 500]],
    ];

    private const PRINTING = [
        [['printings', 1500]],
        [['finishings', 1500]],
    ];

    /** Folded by the toggle. */
    private const BAG = [
        [['manufacturer_id', 625], ['bag_schedule', 875]],
        [['width', 250], ['height', 250], ['flap', 250], ['bottom', 250], ['unfolded_width', 250], ['unfolded_height', 250]],
        [['envelope_shape', 250], ['size_no', 125], ['envelope_other', 375], ['flap_type', 250], ['pasting', 250], ['as_per_artwork', 250]],
        [['die_cut', 375], ['flap_glue', 375], ['side_glue', 375], ['die', 375]],
        [['window', 250], ['window_width', 250], ['window_height', 250], ['window_from_left', 250], ['window_from_bottom', 250]],
        [['carton_number', 250], ['per_case', 250], ['per_case_specified', 250], ['partition_case', 250], ['band', 125], ['band_sheets', 250], ['has_label', 125]],
        [['bag_unit_price', 250], ['bag_manual', 250], ['bag_manual_amount', 250], ['bag_amount', 250], ['bag_subtotal', 250]],
        [['case_cost', 250], ['shipping_cost', 250]],
    ];

    private const SALES = [
        'label:売上・納品',
        [['sales_amount', 375], ['estimated_cost_total', 375], ['estimated_profit', 250], ['profit', 250], ['profit_rate', 250]],
        [['printed_on', 250], ['print_location', 375], ['delivered_on', 250], ['delivery_location', 375], ['delivery_service', 250]],
        [['completed_on', 250], ['remarks', 1250]],
        [['past_orders', 1500]],
    ];

    private const OTHER = [
        [['memo', 500], ['worker_message', 500], ['notes', 500]],
        [['work_sheet_order_files', 500], ['work_sheet_sales_files', 500], ['work_sheet_draft_files', 500]],
        'sub:原価',
        [['internal_cost', 375], ['purchase_total', 375], ['actual_cost_total', 375]],
        [['daily_reports', 1500]],
        [['purchase_history', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const COSTS = [
        [['purchase_amount', 193], ['purchase_cutting_amount', 193], ['purchase_plate_amount', 193], ['purchase_stored_plate_amount', 193], ['purchase_spot_color_amount', 193], ['purchase_cost_total', 193]],
        [['actual_sales_amount', 193], ['sold_amount', 193]],
    ];

    private const DEVELOPER = [
        [['previous_order_date', 117], ['previous_order_number', 193], ['previous_order_quantity', 193]],
        [['machine_id', 193], ['customer_code', 193], ['customer_rounding', 193], ['manufacturing_sales', 193]],
        'sub:版下',
        [['data_supply', 280], ['data_supplier_id', 420], ['designer_id', 299]],
        [['film_supply', 280], ['film_supplier_id', 420]],
        [['legacy_size_no', 137], ['legacy_id', 196]],
    ];

    /**
     * @param  callable(string): ?Component  $make  builds the form input or record entry of an attribute (null = not shown)
     * @param  bool  $withFooter  受注入力ID / 作成者 / 更新者 row (record page only)
     * @param  bool  $withDeveloperFields  開発者用フィールド (super admins only)
     * @return list<Component>
     */
    public static function components(callable $make, bool $withFooter, bool $withDeveloperFields): array
    {
        $components = [
            self::section(self::rows(self::HEAD, $make)),
            self::section(self::rows(self::SALES, $make)),
            self::section(['label:用　紙', ...self::rows(self::PAPER, $make)]),
            self::section(['label:版 下・製 版・刷 版', ...self::rows(self::PLATE, $make)]),
            self::section(['label:印　刷', ...self::rows(self::PRINTING, $make)]),
            self::section([
                Group::make([
                    Text::make('製 袋・製 本・他 加 工')->extraAttributes(['class' => 'sfk-order-label']),
                    View::make('orders::filament.bag-toggle'),
                ])->extraAttributes(['class' => 'sfk-order-bag-head'])->columnSpanFull(),
                Group::make(self::rows(self::BAG, $make))->extraAttributes(['x-show' => 'bagOpen', 'class' => 'sfk-order-bag'])->columnSpanFull(),
            ])->extraAttributes(['x-data' => '{ bagOpen: true }'], merge: true),
            self::section(['label:その他', ...self::rows(self::OTHER, $make)]),
        ];

        if ($withFooter) {
            $components[] = self::section(self::rows(self::FOOTER, $make));
        }

        $components[] = Section::make('各種原価')
            ->schema(self::withLabels(self::rows(self::COSTS, $make)))
            ->extraAttributes(['class' => 'kt-record'])
            ->columnSpanFull();

        if ($withDeveloperFields) {
            $components[] = Section::make('開発者用フィールド')
                ->collapsed()
                ->schema(self::withLabels(self::rows(self::DEVELOPER, $make)))
                ->extraAttributes(['class' => 'kt-record'])
                ->columnSpanFull();
        }

        return $components;
    }

    public static function label(string $attribute): string
    {
        return OrderFields::label($attribute);
    }

    /** @param  list<Component|string>  $schema */
    private static function section(array $schema): Section
    {
        return Section::make()->schema(self::withLabels($schema))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
    }

    /**
     * Legacy label fields become label texts: 'label:…' the section labels with the orange mark of the legacy form,
     * 'sub:…' the plain bold labels inside a group.
     *
     * @param  list<Component|string>  $schema
     * @return list<Component>
     */
    private static function withLabels(array $schema): array
    {
        return array_map(function (Component|string $item): Component {
            if (! is_string($item)) {
                return $item;
            }

            [$kind, $text] = explode(':', $item, 2);

            return Text::make($text)->extraAttributes(['class' => $kind === 'label' ? 'sfk-order-label' : 'sfk-order-sublabel'])->columnSpanFull();
        }, $schema);
    }

    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows
     * @param  callable(string): ?Component  $make
     * @return list<Component|string>
     */
    private static function rows(array $rows, callable $make): array
    {
        $built = [];
        $levels = FieldAccess::onCurrentPage();
        foreach ($rows as $row) {
            if (is_string($row)) {
                $built[] = $row;

                continue;
            }

            $cells = [];
            $shown = false;
            foreach ($row as [$attribute, $width]) {
                $component = $attribute === null ? null : RecordSections::gate($attribute, $make($attribute), $levels);
                if ($attribute !== null && $component === null) {
                    continue;
                }
                $shown = $shown || $component !== null;
                $cells[] = [$component, $width];
            }

            if ($shown) {
                $built[] = RecordLayout::row($cells);
            }
        }

        return $built;
    }
}
