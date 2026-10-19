<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\Orders;

/**
 * The option lists of 04. 受注入力 exactly as in the legacy app (SYSTEM_BRAIN APP_04 fields.md), in legacy order. Values
 * are stored as they are (the legacy labels), so imported data and other apps (12 製袋予定管理 copies 抜き / 窓 / 口糊)
 * keep matching.
 */
final class OrderChoices
{
    /** ステータス (ステータス_0): production steps in order (APP_04 workflows.md), then 作業完了 / 発送完了. */
    public const STATUSES = [
        '断裁作業中', '断裁完了', '抜き作業中', '抜き完了', '窓作業中', '窓完了', '製袋作業中', '製袋完了', '糊作業中', '糊完了',
        '内職作業中', '内職完了', '作業完了', '発送完了',
    ];

    public const STATUS_SHIPPED = '発送完了';

    /** Statuses left out of 製袋予定表出力 (gluing done or later). */
    public const STATUSES_AFTER_GLUING = ['糊完了', '内職作業中', '内職完了', '作業完了', '発送完了', '糊作業中'];

    /** 分類1: product class. */
    public const CATEGORIES1 = [
        '封筒(別注)', '封筒(既製)', '紙ファイル', '一般印刷', '名刺', 'パッケージ', 'はがき', 'ケース', 'チラシ', 'プラ封筒', '紙のみ',
        '通帳', '送料', 'その他',
    ];

    /** 分類2: paper supply. */
    public const CATEGORIES2 = ['紙持ち', '紙支給', 'その他'];

    /** 単位 (default 枚). */
    public const UNITS = ['式', '枚', 'kg', '本', '冊', '個', 'セット', 'その他'];

    public const DEFAULT_UNIT = '枚';

    /** FSC: the one certificate code of the check box (BR-OR-12). */
    public const FSC_CODE = '115';

    /** 用紙情報 目. */
    public const GRAINS = ['T目', 'Y目'];

    /** 用紙情報 計算方法: per sheet or per kg (BR-OR-02). */
    public const PRICING_SHEET = '枚';

    public const PRICING_KG = 'キロ';

    public const PRICINGS = [self::PRICING_SHEET, self::PRICING_KG];

    /** 新旧版区分. */
    public const PLATE_VERSIONS = ['新', '旧', 'CTP'];

    /** 保存区分 (required, default 不要). */
    public const PLATE_STORAGES = ['要', '不要'];

    public const DEFAULT_PLATE_STORAGE = '不要';

    /** 版区分. */
    public const PLATE_SIZES = ['軽オフ', 'QM46', 'GTOF', 'ベビー', 'A全版', '菊全版', '4/6全版', 'その他'];

    /** 製袋予定規格: machine / method of bag making. */
    public const BAG_SCHEDULES = [
        '1号機 1-11-2(40㎜巾～)', '2・5・6号機 190㎜巾まで', '3号機 T240(245㎜巾まで)', '4号機 T310(310㎜巾まで)',
        '平台センター貼410㎜まで(最小120)', 'カマス貼 1F・2F', 'ダイヤ貼', 'AD・TPホットM(アドへヤ・両面テープ・ホットメルト)',
        '手貼・包装・帯掛・(抜・開封・断才)のみ', '外注(協力会社発注)',
    ];

    /** 角・長・洋. */
    public const ENVELOPE_SHAPES = ['角', '長', '洋', 'その他'];

    /** 貼方. */
    public const PASTINGS = ['センター中貼', 'サイド左貼', '逆サイド右貼', '筒貼', '内カマス', '外カマス', 'ダイヤ', 'ストレート(ベタ貼)', '2ツ折・3ツ折'];

    /** ふた区分 (ドロップダウン_1). */
    public const FLAP_TYPES = ['天折', '天スジ・天立', '天スジ無し', '開封 天折', '開封 天スジ', 'ふた無し', '裏爪抜き', '表爪抜き'];

    /** 抜き. */
    public const DIE_CUTS = ['エキセン', '角丸30エキセン', '角丸35エキセン', '角丸40エキセン', 'エキセン+爪抜き', 'トムソン', 'ビク抜き', '丸タガネポンス', '開封エキセン'];

    /** 口糊. */
    public const FLAP_GLUES = [
        '口糊無', '黄色アラビア', '透明アラビア', 'アドヘア', '両面テープ10㎜', '両面テープ15㎜', '両面テープ20㎜', 'HM10㎜', 'HM12㎜', 'HM15㎜',
        '耳出テープ 天地', '耳出テープ 左右',
    ];

    /** 胴糊. */
    public const SIDE_GLUES = ['PP用', '確認中'];

    /** 窓. */
    public const WINDOWS = [
        'セロ窓', 'セロ窓(2つ窓)', 'グラシン窓', 'グラシン窓(2つ窓)', 'セロ134', 'グラシン窓134', '低反射フィルム', '生分解性フィルム', '再生エコフィルム',
    ];

    /** データ支給 / フィルム支給 (fields kept from the legacy app, no longer used there). */
    public const SUPPLIES = ['支給', '自社', '不要'];

    public const DEFAULT_SUPPLY = '支給';

    /**
     * value => label for a select (the legacy label is the stored value).
     *
     * @param  list<string>  $choices
     * @return array<string, string>
     */
    public static function options(array $choices): array
    {
        return array_combine($choices, $choices);
    }
}
