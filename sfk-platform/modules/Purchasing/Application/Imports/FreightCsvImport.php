<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Imports;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Models\Order;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Domain\Imports\CsvSheet;
use Modules\Purchasing\Domain\PurchasingFields;
use Modules\Purchasing\Models\FreightCharge;

/**
 * ファイルから読み込む of 15. 運送請求データ取込: a carrier's billing data from a CSV file with the legacy field labels as
 * headers (日付, 注番, 金額, 運送会社番号, 備考, 単価, 数量, 断裁金額, 版代, 置き版代, 特色). 注番 must be a job and 日付 a
 * date (the legacy 日付 was free text). Every line is checked first — with any problem nothing is imported and the
 * problems are named by line (16_REBUILD_SPEC "freight import").
 */
final class FreightCsvImport
{
    private const MAX_PROBLEMS = 20;

    private const DECIMALS = ['unit_price', 'quantity'];

    private const AMOUNTS = ['amount', 'cutting_amount', 'plate_amount', 'stored_plate_amount', 'spot_color_amount'];

    /** @return int the number of records imported */
    public function import(string $contents): int
    {
        $sheet = CsvSheet::parse($contents);
        $labels = PurchasingFields::FREIGHT;
        if (! $sheet->has($labels['amount'])) {
            throw new PurchasingActionNotAllowed('必要な列がありません: '.$labels['amount']);
        }
        if ($sheet->rows === []) {
            throw new PurchasingActionNotAllowed('読み込むレコードがありません。');
        }

        $orders = Order::query()->whereIn('order_number', array_filter(array_column($sheet->rows, $labels['order_id'])))->pluck('id', 'order_number');
        $records = [];
        $problems = [];

        foreach ($sheet->rows as $line => $row) {
            $value = fn (string $attribute): string => $row[$labels[$attribute]] ?? '';
            $problem = function (string $message) use (&$problems, $line): void {
                $problems[] = $line.'行目: '.$message;
            };

            $date = null;
            if ($value('charged_on') !== '') {
                $date = CsvSheet::date($value('charged_on'));
                if ($date === null) {
                    $problem($labels['charged_on'].'が日付ではありません。');
                }
            }

            $orderId = null;
            if ($value('order_id') !== '') {
                $orderId = $orders[$value('order_id')] ?? null;
                if ($orderId === null) {
                    $problem($labels['order_id'].'「'.$value('order_id').'」の受注がありません。');
                }
            }

            $record = [
                'charged_on' => $date,
                'order_id' => $orderId,
                'carrier_number' => $value('carrier_number') !== '' ? mb_substr($value('carrier_number'), 0, 64) : null,
                'notes' => $value('notes') !== '' ? mb_substr($value('notes'), 0, 255) : null,
            ];
            foreach ([...self::DECIMALS, ...self::AMOUNTS] as $attribute) {
                if ($value($attribute) === '') {
                    $record[$attribute] = null;

                    continue;
                }
                $number = CsvSheet::number($value($attribute));
                if ($number === null) {
                    $problem($labels[$attribute].'が数値ではありません。');
                }
                $record[$attribute] = in_array($attribute, self::AMOUNTS, true) ? (int) round((float) $number) : $number;
            }
            $records[] = $record;
        }

        if ($problems !== []) {
            $more = count($problems) > self::MAX_PROBLEMS ? ['ほか'.(count($problems) - self::MAX_PROBLEMS).'件'] : [];

            throw new PurchasingActionNotAllowed(implode("\n", [...array_slice($problems, 0, self::MAX_PROBLEMS), ...$more]));
        }

        DB::transaction(function () use ($records): void {
            foreach ($records as $attributes) {
                FreightCharge::query()->create($attributes);
            }
        });

        return count($records);
    }
}
