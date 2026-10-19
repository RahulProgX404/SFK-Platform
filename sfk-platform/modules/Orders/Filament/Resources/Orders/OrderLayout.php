<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\Orders;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\Orders\Domain\Orders\OrderFields;

/**
 * The legacy form of 04. 受注入力 (template ZIP layout, SYSTEM_BRAIN APP_04 fields.md "Form layout"), section by section
 * with the legacy labels (orange mark) 用　紙 / 版 下・製 版・刷 版 / 印　刷 / 製 袋・製 本・他 加 工 / その他, the groups 各種原価 and
 * 開発者用フィールド, and the field widths of the template. Very long legacy rows are split where they would not fit
 * the 1,500 px canvas. The form and the record page both build from it; `$make` returns null for what a screen does
 * not show (e.g. the related lists in the form), and such cells / rows are left out.
 *
 * The bag-making fields (仕入先(製造場所) … 送料) fold with 「▲ 折りたたむ」 / 「▼ 展開する」 (legacy toggle script, open
 * at first).
 */
final class OrderLayout
{
    private const HEAD = [
        [['status', 196]],
        [['order_number', 193], ['assignees', 344], ['customer_id', 420], ['closing_day', 141]],
        [['product_name', 387], ['is_fsc', 108], ['category1', 196], ['category2', 196]],
        [['quantity', 117], ['unit', 117], ['sample', 117], ['spare', 117], ['due_date', 117], ['due_time', 117], ['order_date', 117], ['expected_hours', 162]],
        [['manufactured_quantity', 117], ['manufacturing_remaining', 117], ['sold_quantity', 117], ['sales_remaining', 117]],
    ];

    private const PAPER = [
        [['papers', 1500]],
        [[null, 1307], ['paper_subtotal', 193]],
    ];

    private const PLATE = [
        'sub:刷版',
        [['plate_supplier_id', 420], ['plate_version', 196], ['plate_last_used_on', 117], ['plate_count', 117], ['plate_unit_price', 169], ['plate_amount', 193]],
        [['plate_storage', 180], ['plate_size', 196], ['design_fee', 155]],
        [['plate_notes', 616]],
    ];

    private const PRINTING = [
        [['printings', 1500]],
        [['finishings', 1500]],
    ];

    /** Folded by the toggle. */
    private const BAG = [
        [['manufacturer_id', 420], ['bag_schedule', 520]],
        [['width', 137], ['height', 137], ['flap', 137], ['bottom', 137], ['unfolded_width', 137], ['unfolded_height', 137]],
        [['envelope_shape', 196], ['envelope_other', 352], ['size_no', 137], ['pasting', 274], ['as_per_artwork', 137], ['flap_type', 196]],
        [['die_cut', 196], ['flap_glue', 196], ['side_glue', 156], ['die', 193]],
        [['window', 196], ['window_width', 137], ['window_height', 137], ['window_from_left', 137], ['window_from_bottom', 137]],
        [['carton_number', 193], ['per_case', 140], ['per_case_specified', 137], ['partition_case', 137], ['band', 137], ['band_sheets', 140], ['has_label', 137]],
        [['bag_unit_price', 193], ['bag_manual', 140], ['bag_manual_amount', 193], ['bag_amount', 193], ['bag_subtotal', 193]],
        [['case_cost', 193], ['shipping_cost', 193]],
    ];

    private const SALES = [
        [['sales_amount', 193], ['estimated_cost_total', 193], ['estimated_profit', 193], ['profit', 193], ['profit_rate', 193]],
        [['printed_on', 117], ['print_location', 269]],
        [['delivered_on', 117], ['delivery_location', 269], ['delivery_service', 269]],
        [['completed_on', 117], ['remarks', 538]],
        [['past_orders', 1500]],
    ];

    private const OTHER = [
        [['memo', 404], ['worker_message', 415], ['notes', 447]],
        [['internal_cost', 193]],
        [['daily_reports', 1500]],
        [['purchase_total', 193]],
        [['purchase_history', 1500]],
        [['actual_cost_total', 193]],
        [['work_sheet_order_files', 207], ['work_sheet_sales_files', 207], ['work_sheet_draft_files', 207]],
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
            self::section(['label:用　紙', ...self::rows(self::PAPER, $make)]),
            self::section(['label:版 下・製 版・刷 版', ...self::rows(self::PLATE, $make)]),
            self::section(['label:印　刷', ...self::rows(self::PRINTING, $make)]),
            self::section([
                Group::make([
                    Text::make('製 袋・製 本・他 加 工')->extraAttributes(['class' => 'sfk-order-label']),
                    View::make('orders::filament.bag-toggle'),
                ])->extraAttributes(['class' => 'sfk-order-bag-head'])->columnSpanFull(),
                Group::make(self::rows(self::BAG, $make))->extraAttributes(['x-show' => 'bagOpen', 'class' => 'sfk-order-bag'])->columnSpanFull(),
                ...self::rows(self::SALES, $make),
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
        foreach ($rows as $row) {
            if (is_string($row)) {
                $built[] = $row;

                continue;
            }

            $cells = [];
            $shown = false;
            foreach ($row as [$attribute, $width]) {
                $component = $attribute === null ? null : $make($attribute);
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
