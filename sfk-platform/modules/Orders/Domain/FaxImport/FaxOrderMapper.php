<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\FaxImport;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Orders\Domain\Orders\OrderChoices;

/**
 * Turns the fields read from a faxed purchase order (the JSON of the extraction prompt) into 04. 受注入力 values — the
 * rules of the legacy 受注書 import, unchanged: 納期 / 数量 + 単位 (手配数) / 品名 = 検印 surname + 印刷名 / 完成日 = the
 * day before 納期 / 持込日 = the handwritten date by フタ仕様 / 製袋予定規格 by the stamp / 備考(刷版) = 加工伝票番号 /
 * 口糊 from 加工方法 / 窓① and 仕上寸法 sizes / 分類1・分類2 for the issuer. Full-width digits, 「空欄」-like and dash-only
 * values are cleaned first. Changed: a 口糊 that is not an option of the field (ハイシール ⇒ 「HM」 without width, an
 * unknown 単位) is left blank with a warning instead of failing the form, and 「ー」 counts as a dash only between digits.
 */
final class FaxOrderMapper
{
    private const BLANK = '/^[（(]?(空欄|未記入|記載なし|なし|不明|判読不能)[)）]?$/u';

    private const DASHES = '/^[-−—–ー]+$/u';

    private const COMPANY_FORMS = '/株式会社|\(株\)|（株）|㈱/u';

    /** Fields searched for a stamp when 検印欄 has none, in order. */
    private const STAMP_FIELDS = [
        '発注担当者欄' => ['赤マーク', '発注担当者欄'],
        '配送先検印' => ['配送先検印', '検印'],
        '発注情報.発注担当者' => ['発注情報', '発注担当者'],
        '発注日欄' => ['赤マーク', '発注日欄'],
        '手書き注記(枠囲み右)' => ['手書き注記', '枠囲み右'],
    ];

    /** 口糊 by keyword of 加工方法 (after the アラビア / テープスチック rules). */
    private const GLUE_KEYWORDS = ['ハイシール' => 'HM', 'スチック' => 'アドヘア', 'スティック' => 'アドヘア', 'ステック' => 'アドヘア'];

    private const NO_GLUE = '口糊無';

    private const DEFAULT_ARABIC = '黄色アラビア';

    private const DEFAULT_TAPE = '両面テープ10㎜';

    /**
     * 発注情報.発行元 as read.
     *
     * @param  array<string, mixed>  $read
     */
    public static function issuer(array $read): string
    {
        return self::clean(self::get($read, '発注情報', '発行元'));
    }

    /** The issuer is the configured company (legal form and spaces ignored, a factory name after it allowed). */
    public static function isFromIssuer(string $issuer, FaxImportRules $rules): bool
    {
        $expected = self::companyKey($rules->issuer);

        return $expected !== '' && str_starts_with(self::companyKey($issuer), $expected);
    }

    /** Company name without 株式会社 / (株) and spaces, for comparing names. */
    public static function companyKey(string $name): string
    {
        return (string) preg_replace('/\s+/u', '', (string) preg_replace(self::COMPANY_FORMS, '', self::clean($name)));
    }

    /**
     * The surname stamped in 検印欄, if one of the configured ones.
     *
     * @param  array<string, mixed>  $read
     */
    public static function inspectionStamp(array $read, FaxImportRules $rules): ?string
    {
        $stamp = self::clean(self::get($read, '赤マーク', '検印欄'));
        if ($stamp === '') {
            return null;
        }

        foreach ($rules->stampNames() as $name) {
            if (str_contains($stamp, $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Configured surnames seen in the other stamp fields: surname => field it was first seen in.
     *
     * @param  array<string, mixed>  $read
     * @return array<string, string>
     */
    public static function stampCandidates(array $read, FaxImportRules $rules): array
    {
        $found = [];
        foreach (self::STAMP_FIELDS as $label => [$group, $key]) {
            $value = self::clean(self::get($read, $group, $key));
            if ($value === '') {
                continue;
            }
            foreach ($rules->stampNames() as $name) {
                if (str_contains($value, $name) && ! isset($found[$name])) {
                    $found[$name] = $label;
                }
            }
        }

        return $found;
    }

    /**
     * The order values for the stamp `$customer` (the issuer's order staff).
     *
     * @param  array<string, mixed>  $read
     */
    public static function map(array $read, string $customer, FaxImportRules $rules, DateTimeImmutable $today): FaxOrderMapping
    {
        $errors = [];
        $year = self::referenceYear($read, (int) $today->format('Y'));
        $marked = (array) ($read['赤丸番号付きデータ'] ?? []);

        $dueDate = self::firstFilled(
            self::date(self::get($read, 'ヘッダー情報', '納品指定'), $year),
            self::date(self::get($read, '発注情報', '納期'), $year),
            self::date(self::scalar($marked['納期'] ?? null), $year),
        );
        if ($dueDate === '') {
            $errors[] = '納期を読み取れませんでした。';
        }

        $arranged = self::firstFilled(self::scalar($marked['手配数'] ?? null), self::get($read, '製品仕様', '手配数'), self::get($read, '製品仕様', '受注数'));
        [$quantity, $unit] = self::amountAndUnit($arranged);
        if ($quantity <= 0) {
            $errors[] = '数量を読み取れませんでした（0より大きい必要があります）。';
        }
        if ($unit === '') {
            $errors[] = '単位を読み取れませんでした。';
        }

        $printName = self::firstFilled(self::scalar($marked['印刷名'] ?? null), self::get($read, '製品仕様', '印刷名'), self::scalar($read['件名'] ?? null));
        if ($printName === '') {
            $errors[] = '印刷名を読み取れませんでした。';
        }

        $completedOn = $dueDate !== '' ? self::addDays($dueDate, -1) : '';
        $flapNote = self::firstFilled(self::scalar($marked['フタ仕様_折り返し寸法'] ?? null), self::get($read, '口糊加工', 'フタ仕様'), self::get($read, '手書き注記', '手書き'));
        $deliveredOn = self::handwrittenDate($flapNote, $dueDate, $year);
        $slipNumber = self::firstFilled(self::scalar($marked['加工伝票番号'] ?? null), self::scalar($read['加工伝票番号'] ?? null), self::get($read, '製造版関連', '版下番号'));
        $issuer = self::issuer($read);
        $fromIssuer = self::isFromIssuer($issuer, $rules);
        $method = self::clean(self::get($read, '口糊加工', '加工方法'));
        $detail = self::clean(self::get($read, '口糊加工', '詳細'));
        [$glue, $glueWarning] = self::flapGlue($method, $detail);
        $schedule = self::bagSchedule($read, $customer, $rules);
        $window = self::firstWindow($read, '①');
        $finish = self::finishSize(self::get($read, '製品仕様', '仕上寸法'));
        $unitWarning = null;
        if ($unit !== '' && ! in_array($unit, OrderChoices::UNITS, true)) {
            $unitWarning = "単位「{$unit}」は選択肢にないため設定していません";
            $unit = '';
        }

        $values = [
            'due_date' => $dueDate,
            'quantity' => $quantity > 0 ? (string) $quantity : '',
            'unit' => $unit,
            'product_name' => $printName !== '' ? "{$customer}　{$printName}" : $customer,
            'completed_on' => $completedOn,
            'manufacturer_name' => $rules->manufacturer,
            'bag_schedule' => $schedule,
            'delivered_on' => $deliveredOn,
            'plate_notes' => $slipNumber,
            'customer_name' => $issuer,
            'flap_glue' => $glue,
            'window_width' => self::dimension($window['ヨコ'] ?? null),
            'window_height' => self::dimension($window['タテ'] ?? null),
            'window_from_left' => self::dimension($window['左より'] ?? null),
            'window_from_bottom' => self::dimension($window['底より'] ?? null),
            'width' => $finish['width'],
            'height' => $finish['height'],
            'flap' => $finish['flap'],
            'category1' => $fromIssuer ? $rules->category1 : '',
            'category2' => $fromIssuer ? $rules->category2 : '',
        ];

        $dueText = self::firstFilled(self::get($read, 'ヘッダー情報', '納品指定'), self::get($read, '発注情報', '納期'), self::scalar($marked['納期'] ?? null));
        $finishText = self::get($read, '製品仕様', '仕上寸法') ?: '—';
        $issuerSource = $fromIssuer ? "得意先(受注先)が「{$rules->issuer}」" : "得意先(受注先)が「{$rules->issuer}」以外のため未設定";
        $row = fn (string $field, string $label, string $source, ?string $warning = null): array => [
            'field' => $field, 'label' => $label, 'value' => $values[$field], 'source' => $source, 'warning' => $warning,
        ];

        $fields = [
            $row('product_name', '品名', "検印欄「{$customer}」＋ 印刷名", $printName !== '' ? null : '印刷名が取れていません'),
            $row('quantity', '数量', '手配数「'.($arranged ?: '—').'」の数値部分', $quantity > 0 ? null : '0より大きい数量が必要です'),
            $row('unit', '単位', '手配数「'.($arranged ?: '—').'」の単位部分', $unitWarning ?? ($values['unit'] !== '' ? null : '単位が取れていません')),
            $row('due_date', '納期', '納期「'.($dueText ?: '—').'」', $dueDate !== '' ? null : '納期が取れていません'),
            $row('completed_on', '完成日', $dueDate !== '' ? "納期 {$dueDate} の前日" : '納期未確定のため算出不可', $completedOn !== '' ? null : '納期が取れていないため算出できません'),
            $row('delivered_on', '持込日', 'フタ仕様／折り返し寸法「'.($flapNote ?: '—').'」の手書き日付', $deliveredOn !== '' ? null : '手書き日付を特定できませんでした'),
            $row('manufacturer_name', '仕入先(製造場所)', '固定値'),
            $row('bag_schedule', '製袋予定規格', $schedule === $rules->prioritySchedule && $schedule !== ($rules->stamps[$customer] ?? null)
                ? "発注担当者欄／発注日欄に「{$rules->priorityStamp}」を検知したため優先設定"
                : "得意先「{$customer}」から決定"),
            $row('plate_notes', '備考(刷版)', '加工伝票番号', $slipNumber !== '' ? null : '加工伝票番号が取れていません'),
            $row('customer_name', '得意先(受注先)', '発注情報.発行元', $issuer !== '' ? null : '発行元が取れていません'),
            $row('category1', '分類1', $issuerSource),
            $row('category2', '分類2', $issuerSource),
            $row('flap_glue', '口糊', '加工方法「'.($method ?: '—').'」から決定', $glueWarning),
            $row('window_width', '窓(左右)', '窓①のヨコ「'.(self::scalar($window['ヨコ'] ?? null) ?: '—').'」', $values['window_width'] !== '' ? null : '窓①のヨコが取れていません'),
            $row('window_height', '窓(天地)', '窓①のタテ「'.(self::scalar($window['タテ'] ?? null) ?: '—').'」', $values['window_height'] !== '' ? null : '窓①のタテが取れていません'),
            $row('window_from_left', '左から', '窓①の左より「'.(self::scalar($window['左より'] ?? null) ?: '—').'」', $values['window_from_left'] !== '' ? null : '窓①の左よりが取れていません'),
            $row('window_from_bottom', '底から', '窓①の底より「'.(self::scalar($window['底より'] ?? null) ?: '—').'」', $values['window_from_bottom'] !== '' ? null : '窓①の底よりが取れていません'),
            $row('width', '左右', "仕上寸法「{$finishText}」のヨコ", $values['width'] !== '' ? null : '仕上寸法のヨコが取れていません'),
            $row('height', '天地', "仕上寸法「{$finishText}」のタテ", $values['height'] !== '' ? null : '仕上寸法のタテが取れていません'),
            $row('flap', 'ふた', "仕上寸法「{$finishText}」のフタ", $values['flap'] !== '' ? null : '仕上寸法のフタが取れていません'),
        ];

        return new FaxOrderMapping($values, $fields, $errors);
    }

    /**
     * 製袋予定規格: the stamp's, unless the priority stamp is written in the order-staff / date / 検印 fields.
     *
     * @param  array<string, mixed>  $read
     */
    private static function bagSchedule(array $read, string $customer, FaxImportRules $rules): string
    {
        foreach ([['赤マーク', '発注担当者欄'], ['赤マーク', '発注日欄'], ['赤マーク', '検印欄']] as [$group, $key]) {
            if ($rules->priorityStamp !== '' && str_contains(self::clean(self::get($read, $group, $key)), $rules->priorityStamp)) {
                return $rules->prioritySchedule;
            }
        }

        return $rules->stamps[$customer] ?? '';
    }

    /**
     * 口糊 and its warning.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function flapGlue(string $method, string $detail): array
    {
        $method = (string) preg_replace('/\s+/u', '', $method);
        if ($method === '') {
            return [self::NO_GLUE, '加工方法が取れていないため「'.self::NO_GLUE.'」を設定しました'];
        }

        if (str_contains($method, 'アラビア')) {
            return match (true) {
                str_contains($detail, '黄色') => ['黄色アラビア', null],
                str_contains($detail, '透明') => ['透明アラビア', null],
                default => [self::DEFAULT_ARABIC, '詳細「'.($detail ?: '—').'」が不鮮明で黄色/透明のどちらか読み取れなかったため、暫定で「'.self::DEFAULT_ARABIC.'」を設定しました。内容をご確認のうえ、手動で設定し直してください。'],
            };
        }

        if (str_contains($method, 'テープスチック')) {
            return match (true) {
                str_contains($detail, '10mm') => ['両面テープ10㎜', null],
                str_contains($detail, '15mm') => ['両面テープ15㎜', null],
                str_contains($detail, '20mm') => ['両面テープ20㎜', null],
                default => [self::DEFAULT_TAPE, '詳細「'.($detail ?: '—').'」が不鮮明で10mm/15mm/20mmのいずれか読み取れなかったため、暫定で「'.self::DEFAULT_TAPE.'」を設定しました。内容をご確認のうえ、手動で設定し直してください。'],
            };
        }

        foreach (self::GLUE_KEYWORDS as $keyword => $glue) {
            if (str_contains($method, $keyword)) {
                return in_array($glue, OrderChoices::FLAP_GLUES, true)
                    ? [$glue, null]
                    : ['', "加工方法「{$method}」の口糊「{$glue}」は幅が分からないため設定していません。手動で設定してください。"];
            }
        }

        return [self::NO_GLUE, "加工方法「{$method}」は変換規則にないため「".self::NO_GLUE.'」を設定しました'];
    }

    /**
     * Year of the order: from 発注日 / 依頼日 / 発注日欄 / FAX 受信日時, else this year.
     *
     * @param  array<string, mixed>  $read
     */
    private static function referenceYear(array $read, int $currentYear): int
    {
        foreach ([['発注情報', '発注日'], ['ヘッダー情報', '依頼日'], ['赤マーク', '発注日欄'], ['FAX送信情報', '受信日時']] as [$group, $key]) {
            $date = self::date(self::get($read, $group, $key), $currentYear);
            if ($date !== '') {
                return (int) substr($date, 0, 4);
            }
        }

        return $currentYear;
    }

    /** A date written as 2026/9/3, 2026年9月3日, 26.9.3 or 9/3 (in `$year`), as Y-m-d; "" when there is none. */
    public static function date(string $value, int $year): string
    {
        $text = self::normalize(self::clean($value));
        if ($text === '') {
            return '';
        }

        if (preg_match('/(\d{4})\s*[\/\-.年]\s*(\d{1,2})\s*[\/\-.月]\s*(\d{1,2})/u', $text, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(?:^|[^\d])(\d{2})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})(?!\d)/u', $text, $m) === 1 && checkdate((int) $m[2], (int) $m[3], 2000 + (int) $m[1])) {
            return self::ymd(2000 + (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(?:^|[^\d\/.\-])(\d{1,2})\s*[\/月]\s*(\d{1,2})\s*日?(?!\s*[\/\-.]?\d)/u', $text, $m) === 1 && checkdate((int) $m[1], (int) $m[2], $year)) {
            return self::ymd($year, (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    /**
     * The handwritten month/day near フタ仕様 (e.g. 手書「8/6」) as 持込日, in the year of 納期; more than 60 days after
     * 納期 means the year before.
     */
    private static function handwrittenDate(string $value, string $dueDate, int $year): string
    {
        $text = self::normalize(self::clean($value));
        if ($text === '') {
            return '';
        }

        $year = $dueDate !== '' ? (int) substr($dueDate, 0, 4) : $year;
        $patterns = [
            '/手書[きが]?[^「【]{0,12}[「【]\s*(\d{1,2})\s*\/\s*(\d{1,2})\s*[」】]/u',
            '/[「【]\s*(\d{1,2})\s*\/\s*(\d{1,2})\s*[」】]/u',
            '/(?:^|[^\d.\-])(\d{1,2})\s*\/\s*(\d{1,2})(?!\s*[\/.\-]?\d)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) !== 1 || ! checkdate((int) $m[1], (int) $m[2], $year)) {
                continue;
            }

            $date = self::ymd($year, (int) $m[1], (int) $m[2]);
            if ($dueDate !== '' && self::daysBetween($date, $dueDate) > 60) {
                $date = self::ymd($year - 1, (int) $m[1], (int) $m[2]);
            }

            return $date;
        }

        return '';
    }

    /**
     * 「12,000 枚」 ⇒ [12000, '枚'].
     *
     * @return array{0: int, 1: string}
     */
    private static function amountAndUnit(string $value): array
    {
        $text = self::normalize(self::clean($value));
        if ($text === '' || preg_match('/(\d[\d,]*)\s*([^\s\d,(（【\[]{1,3})?/u', $text, $m) !== 1) {
            return [0, ''];
        }

        return [(int) str_replace(',', '', $m[1]), trim($m[2] ?? '')];
    }

    /** The first number of a size, with 2 decimals; "" when there is none. */
    private static function dimension(mixed $value): string
    {
        $text = self::normalize(self::clean(self::scalar($value)));
        if ($text === '' || preg_match('/-?\d+(?:\.\d+)?/', $text, $m) !== 1) {
            return '';
        }

        return number_format((float) $m[0], 2, '.', '');
    }

    /**
     * 仕上寸法 「ヨコ 235 タテ 120 フタ 30」 (or just three numbers) ⇒ 左右 / 天地 / ふた.
     *
     * @return array{width: string, height: string, flap: string}
     */
    private static function finishSize(string $value): array
    {
        $text = self::normalize(self::clean($value));
        if ($text === '') {
            return ['width' => '', 'height' => '', 'flap' => ''];
        }

        $find = fn (string $name): ?string => preg_match('/'.$name.'\s*(-?\d+(?:\.\d+)?)/u', $text, $m) === 1 ? $m[1] : null;
        [$width, $height, $flap] = [$find('ヨコ'), $find('タテ'), $find('フタ')];

        if ($width === null && $height === null && $flap === null) {
            preg_match_all('/\d+(?:\.\d+)?/', $text, $all);
            [$width, $height, $flap] = array_pad($all[0], 3, null);
        }

        return ['width' => self::dimension($width), 'height' => self::dimension($height), 'flap' => self::dimension($flap)];
    }

    /**
     * @param  array<string, mixed>  $read
     * @return array<string, mixed> the window whose 番号 has `$number`, else the first one
     */
    private static function firstWindow(array $read, string $number): array
    {
        $windows = array_values(array_filter((array) (((array) ($read['窓仕様'] ?? []))['窓'] ?? []), 'is_array'));
        foreach ($windows as $window) {
            if (str_contains(self::clean(self::scalar($window['番号'] ?? null)), $number)) {
                return $window;
            }
        }

        return $windows[0] ?? [];
    }

    /** Half-width digits and letters, ASCII separators; 「ー」 is a dash only between digits (「ケース」 stays). */
    private static function normalize(string $value): string
    {
        $value = (string) preg_replace_callback('/[０-９Ａ-Ｚａ-ｚ]/u', fn (array $m): string => mb_chr(mb_ord($m[0]) - 65248), $value);
        $value = (string) preg_replace(['/[，、]/u', '/[－―–—]/u', '/(?<=\d)ー(?=\d)/u'], [',', '-', '-'], $value);

        return strtr($value, ['／' => '/', '．' => '.']);
    }

    /** Trimmed value; 「空欄」, 「なし」, 「判読不能」 … and dashes count as blank. */
    private static function clean(string $value): string
    {
        $value = trim($value);

        return preg_match(self::BLANK, $value) === 1 || preg_match(self::DASHES, $value) === 1 ? '' : $value;
    }

    private static function firstFilled(string ...$values): string
    {
        foreach ($values as $value) {
            $value = self::clean($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @param  array<string, mixed>  $read */
    private static function get(array $read, string $group, string $key): string
    {
        $values = $read[$group] ?? null;

        return is_array($values) ? self::scalar($values[$key] ?? null) : '';
    }

    private static function scalar(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    private static function ymd(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify(($days >= 0 ? '+' : '').$days.' day')->format('Y-m-d');
    }

    /** Days from `$to` to `$from` (positive when `$from` is later). */
    private static function daysBetween(string $from, string $to): int
    {
        $utc = new DateTimeZone('UTC');

        return (int) round(((new DateTimeImmutable($from, $utc))->getTimestamp() - (new DateTimeImmutable($to, $utc))->getTimestamp()) / 86400);
    }
}
