<?php

declare(strict_types=1);

namespace Modules\Purchasing\Domain\Payables;

use Modules\MasterData\Domain\Partners\PurchaseCategory;

/**
 * 仕入カテゴリ別内訳 of 23. 買掛管理: the breakdown columns (`cat_<key>` of payable_months, legacy labels and order of
 * the template) and the purchase categories each one sums (仕入金額合計 by 仕入カテゴリ判定, BR-PU-04). The legacy flows
 * only matched the plain categories; here a 「(否インボス)」 category counts in its column too, 13.原運送代 / 14 in 原配送
 * and 23.運送代 in 配送 (they were in no column, APP_05 / APP_23B known issues), and 42.雑包装費 has its own column.
 */
final class PayableCategories
{
    /** Column => label. */
    public const LABELS = [
        'cat_materials' => '材料仕入',
        'cat_goods' => '商品仕入',
        'cat_outsourcing' => '外注',
        'cat_cost_supplies' => '原消耗品',
        'cat_cost_repairs' => '原修繕費',
        'cat_cost_delivery' => '原配送',
        'cat_cost_misc' => '原雑費',
        'cat_cost_packaging' => '原包装費',
        'cat_supplies' => '消耗品',
        'cat_repairs' => '修繕費',
        'cat_delivery' => '配送',
        'cat_misc' => '雑費',
        'cat_packaging' => '包装費',
        'cat_office_supplies' => '事務消耗品',
        'cat_expenses' => '経費',
        'cat_misc_misc' => '雑雑費',
        'cat_misc_cost_repairs' => '雑原修繕費',
        'cat_misc_repairs' => '雑修繕費',
        'cat_misc_cost_supplies' => '雑原消耗品',
        'cat_misc_supplies' => '雑消耗品',
        'cat_misc_materials' => '雑材料仕入',
        'cat_misc_goods' => '雑商品仕入',
        'cat_misc_freight' => '雑運送費',
        'cat_misc_outsourcing' => '雑外注',
        'cat_misc_expenses' => '雑経費',
        'cat_misc_packaging' => '雑包装費',
    ];

    private const CATEGORIES = [
        'cat_materials' => [PurchaseCategory::C03, PurchaseCategory::C04],
        'cat_goods' => [PurchaseCategory::C01, PurchaseCategory::C02],
        'cat_outsourcing' => [PurchaseCategory::C05, PurchaseCategory::C06],
        'cat_cost_supplies' => [PurchaseCategory::C09, PurchaseCategory::C10],
        'cat_cost_repairs' => [PurchaseCategory::C07, PurchaseCategory::C08],
        'cat_cost_delivery' => [PurchaseCategory::C13, PurchaseCategory::C14],
        'cat_cost_misc' => [PurchaseCategory::C15, PurchaseCategory::C16],
        'cat_cost_packaging' => [PurchaseCategory::C11, PurchaseCategory::C12],
        'cat_supplies' => [PurchaseCategory::C17, PurchaseCategory::C18],
        'cat_repairs' => [PurchaseCategory::C19, PurchaseCategory::C20],
        'cat_delivery' => [PurchaseCategory::C23, PurchaseCategory::C24, PurchaseCategory::C25],
        'cat_misc' => [PurchaseCategory::C26, PurchaseCategory::C27],
        'cat_packaging' => [PurchaseCategory::C28, PurchaseCategory::C29],
        'cat_office_supplies' => [PurchaseCategory::C21, PurchaseCategory::C22],
        'cat_expenses' => [PurchaseCategory::C30, PurchaseCategory::C31],
        'cat_misc_misc' => [PurchaseCategory::C32],
        'cat_misc_cost_repairs' => [PurchaseCategory::C33],
        'cat_misc_repairs' => [PurchaseCategory::C34],
        'cat_misc_cost_supplies' => [PurchaseCategory::C35],
        'cat_misc_supplies' => [PurchaseCategory::C36],
        'cat_misc_materials' => [PurchaseCategory::C37],
        'cat_misc_goods' => [PurchaseCategory::C38],
        'cat_misc_freight' => [PurchaseCategory::C39],
        'cat_misc_outsourcing' => [PurchaseCategory::C40],
        'cat_misc_expenses' => [PurchaseCategory::C41],
        'cat_misc_packaging' => [PurchaseCategory::C42],
    ];

    /** @return list<string> the breakdown columns */
    public static function columns(): array
    {
        return array_keys(self::LABELS);
    }

    /** The column a purchase category counts in (null: none, e.g. no category). */
    public static function columnFor(?PurchaseCategory $category): ?string
    {
        foreach (self::CATEGORIES as $column => $categories) {
            if (in_array($category, $categories, true)) {
                return $column;
            }
        }

        return null;
    }
}
