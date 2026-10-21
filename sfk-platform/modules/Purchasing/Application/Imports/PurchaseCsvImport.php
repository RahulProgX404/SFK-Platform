<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Imports;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\Imports\CsvSheet;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\Purchase;

/**
 * ファイルから読み込む of 05. 納品(仕入)入力: supplier invoice lines from a CSV file with the legacy field labels as
 * headers (the legacy app was filled by CSV import, SYSTEM_BRAIN APP_05 README; 16_REBUILD_SPEC "CSV import with
 * validation and posting"). 仕入先 is the 取引先名 of a 仕入先, 受注No a 注番; 金額 left empty is 数量 × 単価 by the supplier's
 * 端数区分(仕入) (BR-PU-02). Every line is checked first — with any problem nothing is imported and the problems are
 * named by line. Imported purchases are numbered (BR-PU-01) and posted like any other: the jobs' 仕入 costs and 23.
 * 買掛管理 are recalculated (no legacy 発番 / 受注レコード更新 / 買掛レコード更新 buttons needed).
 */
final class PurchaseCsvImport
{
    /** At most this many problems are reported. */
    private const MAX_PROBLEMS = 20;

    /** Header label => amount attribute (whole yen, default 0). */
    private const AMOUNTS = [
        '断裁金額' => 'cutting_amount',
        '版代・木型代' => 'plate_amount',
        '置き版代・セット代・その他' => 'stored_plate_amount',
        '特色・色替・CPU・その他' => 'spot_color_amount',
    ];

    /** @return int the number of purchases imported */
    public function import(string $contents): int
    {
        $sheet = CsvSheet::parse($contents);
        $labels = PurchasingFields::PURCHASE;
        $missing = array_values(array_filter([$labels['purchased_on'], $labels['supplier_id']], fn (string $header): bool => ! $sheet->has($header)));
        if (! $sheet->has($labels['amount']) && ! ($sheet->has($labels['quantity']) && $sheet->has($labels['unit_price']))) {
            $missing[] = $labels['amount'];
        }
        if ($missing !== []) {
            throw new PurchasingActionNotAllowed('必要な列がありません: '.implode('、', $missing));
        }
        if ($sheet->rows === []) {
            throw new PurchasingActionNotAllowed('読み込むレコードがありません。');
        }

        $suppliers = Partner::query()->where('is_supplier', true)->get()->groupBy('name');
        $orders = Order::query()->whereIn('order_number', array_filter(array_column($sheet->rows, $labels['order_id'])))->pluck('id', 'order_number');
        $numbers = [];
        $records = [];
        $problems = [];

        foreach ($sheet->rows as $line => $row) {
            $value = fn (string $attribute): string => $row[$labels[$attribute]] ?? '';
            $problem = function (string $message) use (&$problems, $line): void {
                $problems[] = $line.'行目: '.$message;
            };

            $date = CsvSheet::date($value('purchased_on'));
            if ($date === null) {
                $problem($labels['purchased_on'].'が日付ではありません。');
            }

            $matches = $suppliers->get($value('supplier_id'));
            $supplier = $matches?->count() === 1 ? $matches->first() : null;
            if ($supplier === null) {
                $problem($labels['supplier_id'].'「'.$value('supplier_id').'」'.($matches === null ? 'が仕入先にありません。' : 'が複数あります。'));
            }

            $orderId = null;
            if ($value('order_id') !== '') {
                $orderId = $orders[$value('order_id')] ?? null;
                if ($orderId === null) {
                    $problem($labels['order_id'].'「'.$value('order_id').'」の受注がありません。');
                }
            }

            $category = null;
            if ($value('category') !== '') {
                $category = PurchaseCategory::tryFrom($value('category'));
                if ($category === null) {
                    $problem($labels['category'].'「'.$value('category').'」は選択肢にありません。');
                }
            }

            $decimal = function (string $attribute) use ($value, $problem, $labels): ?string {
                if ($value($attribute) === '') {
                    return null;
                }
                $number = CsvSheet::number($value($attribute));
                if ($number === null) {
                    $problem($labels[$attribute].'が数値ではありません。');
                }

                return $number;
            };
            $quantity = $decimal('quantity');
            $price = $decimal('unit_price');
            $amount = $decimal('amount');
            if ($amount === null && $quantity !== null && $price !== null) {
                $amount = (string) Yen::round(Yen::multiply($quantity, $price), RoundingMode::fromLabel($supplier?->supplier_rounding?->value));
            }
            if ($amount === null) {
                $problem($labels['amount'].'がありません。');
            }

            $components = [];
            foreach (self::AMOUNTS as $header => $attribute) {
                $component = CsvSheet::number($row[$header] ?? '') ?? (($row[$header] ?? '') === '' ? '0' : null);
                if ($component === null) {
                    $problem($header.'が数値ではありません。');
                }
                $components[$attribute] = (int) round((float) $component);
            }

            $number = $value('purchase_number');
            if ($number !== '') {
                if (isset($numbers[$number]) || Purchase::query()->where('purchase_number', $number)->exists()) {
                    $problem($labels['purchase_number'].'「'.$number.'」は既に使われています。');
                }
                $numbers[$number] = true;
            }

            $records[] = [
                'purchased_on' => $date,
                'supplier_id' => $supplier?->id,
                'order_id' => $orderId,
                'category' => $category?->value,
                'purchase_number' => $number !== '' ? $number : null,
                'product_name' => $value('product_name') !== '' ? mb_substr($value('product_name'), 0, 255) : null,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => (int) round((float) $amount),
                ...$components,
                'is_fsc' => $value('is_fsc') === PurchaseChoices::FSC_CODE,
                'period_end' => $value('period_end') === PurchaseChoices::PERIOD_END,
            ];
        }

        if ($problems !== []) {
            $more = count($problems) > self::MAX_PROBLEMS ? ['ほか'.(count($problems) - self::MAX_PROBLEMS).'件'] : [];

            throw new PurchasingActionNotAllowed(implode("\n", [...array_slice($problems, 0, self::MAX_PROBLEMS), ...$more]));
        }

        DB::transaction(function () use ($records): void {
            foreach ($records as $attributes) {
                Purchase::query()->create($attributes);
            }
        });

        return count($records);
    }
}
