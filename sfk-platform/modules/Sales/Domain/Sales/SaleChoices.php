<?php

declare(strict_types=1);

namespace Modules\Sales\Domain\Sales;

/**
 * Choices of 06. 売上入力 exactly as in the legacy app (SYSTEM_BRAIN APP_06 fields.md), stored as their labels.
 */
final class SaleChoices
{
    /** 区分 (default 通常売上). マイナス処理 is a credit: it may go on a fully sold job (BR-SA-02). */
    public const KINDS = ['通常売上', 'マイナス処理', '版代', '送料', 'その他'];

    public const KIND_NORMAL = '通常売上';

    public const KIND_CREDIT = 'マイナス処理';

    /** 単位 (default 枚), as 04. 受注入力. */
    public const UNITS = ['式', '枚', 'kg', '本', '冊', '個', 'セット', 'その他'];

    public const DEFAULT_UNIT = '枚';

    /** 請求締日変更: the only override of the customer's closing day (BR-SA-06). */
    public const CLOSING_DAY_OVERRIDES = ['随時請求'];

    /** 必要なもの (即時依頼等). */
    public const NEEDS = ['納品書', '請求書', '領収書', 'その他'];

    /** 受け渡し (即時依頼等). */
    public const HANDOVERS = ['営業宛にほしい(紙)', '営業宛にほしい(PDF)', '先方に送ってほしい(郵送)', '先方に送ってほしい(メール)', 'その他'];

    /** 送付 of 納品書 / 請求書 (default 未). */
    public const SEND_STATUSES = ['未', '未送済', '済'];

    /** 請求区分 of 請求書 (default 通常請求). */
    public const INVOICE_KINDS = ['通常請求', '返金処理'];

    /** 入金方法 / 銀行 of 入金明細 (APP_21). */
    public const PAYMENT_METHODS = ['振込', '手形', '小切手', '現金', '相殺'];

    public const BANKS = ['三菱UFJ銀行', '三井住友銀行', '大阪商工信用金庫', '池田泉州銀行'];

    /** Qualified-invoice registration number of the company printed on 納品書 / 請求書 (BR-TX-08). */
    public const REGISTRATION_NUMBER = 'T-1200-0102-0414';

    /** FSC コードNo (BR-OR-12). */
    public const FSC_CODE = '115';

    /**
     * @param  list<string>  $choices
     * @return array<string, string>
     */
    public static function options(array $choices): array
    {
        return array_combine($choices, $choices);
    }
}
