<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Dashboard;

use Modules\Analytics\Domain\Period;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Support\Tip;

/**
 * The tooltips of the dashboard's figures (user 2026-11-14: "only show important things"): what a figure is in one
 * short line, and for a comparison the two periods and the difference — nothing the card already shows next to it.
 * Written from the rules the figures follow — SalesDashboard (売上日 in the period, 売上金額 税抜, the first 担当者 for
 * 個人, the payment status of a sale), Period (前期間 = the same number of days just before, 前年同期 = the same days a
 * year earlier) and SalesCoverage — and from the card's own values: nothing here reads the database.
 */
final class DashboardTips
{
    /** what each payment status of SalesDashboard::STATUSES is (SalesDashboard::status()) */
    public const STATUS_TEXT = [
        'paid' => '請求額の入金が済んだ売上',
        'pending' => '請求済み・未入金で、回収予定日前の売上',
        'overdue' => '請求済み・未入金で、回収予定日を過ぎた売上',
        'unbilled' => 'まだ請求書に載っていない売上',
    ];

    /** 売上合計 / 売上金額（税抜）. */
    public static function salesAmount(string $title, bool $personal): Tip
    {
        return Tip::make($title)->line('選択期間の売上金額（税抜）の合計'.($personal ? '（自分が最初の担当者の売上）' : ''));
    }

    /** 売上件数. */
    public static function salesCount(bool $personal): Tip
    {
        return Tip::make('売上件数')->line('選択期間の売上の件数'.($personal ? '（自分が最初の担当者の売上）' : ''));
    }

    /** 売上目標 (全体) or 売上予想 (個人). */
    public static function target(string $label, bool $personal): Tip
    {
        return Tip::make($label)->line(($personal ? '売上分析の自分の月別予想' : '販売先別売上計画の月別計画').'のうち、選択期間の日数分の合計');
    }

    /**
     * A comparison chip (前期間比, 前年同期比, 前年度同期比): the two periods with their values and the difference.
     *
     * @param  string  $baseName  前期間 / 前年同期 / 前年度同期
     */
    public static function comparison(string $baseName, Period $now, Period $base, int $nowValue, int $baseValue, bool $yen): Tip
    {
        $difference = $nowValue - $baseValue;
        $format = fn (int $value): string => $yen ? AppFormat::yen($value) : number_format($value).'件';
        $sign = $difference > 0 ? '+' : ($difference < 0 ? '−' : '±');

        return Tip::make($baseName.'比')
            ->row('当期（'.$now->shortLabel().'）', $format($nowValue))
            ->row($baseName.'（'.$base->shortLabel().'）', $format($baseValue))
            ->row($yen ? '増減額' : '増減数', $yen ? Tip::signedYen($difference) : $sign.number_format(abs($difference)).'件', tone: Tip::tone($difference), strong: true);
    }

    /** A payment status of 売上概要 (入金済 / 入金待ち / 期限超過 / 未請求). */
    public static function status(string $key, string $label): Tip
    {
        return Tip::make($label)->line(self::STATUS_TEXT[$key]);
    }

    /** 入金 / 出金 / 差引 of 入出金. */
    public static function cash(string $key, bool $personal): Tip
    {
        return match ($key) {
            'inflow' => Tip::make('入金')->line('入金日が選択期間内の入金額の合計'.($personal ? '（自分が担当者の入金）' : '')),
            'outflow' => Tip::make('出金')->line('支払日が選択期間内の支払額の合計'),
            default => Tip::make('差引')->line('入金 − 出金'),
        };
    }

    /**
     * A row of a ranking: the full name (long names are cut on the card) and its share of the whole.
     *
     * @param  list<array{0: string, 1: string}>  $extra  more label / value rows
     */
    public static function ranking(string $name, ?int $share, array $extra = []): Tip
    {
        $tip = Tip::make($name);
        if ($share !== null) {
            $tip->row('構成比', $share.'%');
        }
        foreach ($extra as [$label, $value]) {
            $tip->row($label, $value);
        }

        return $tip;
    }

    /** The share of a whole in whole percent; null without a whole. */
    public static function share(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }
}
