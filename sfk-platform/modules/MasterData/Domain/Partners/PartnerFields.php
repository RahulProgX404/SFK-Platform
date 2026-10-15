<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

/**
 * Field labels of 01. 取引先マスタ exactly as in the legacy system (SYSTEM_BRAIN APP_01 fields.md), by attribute. Used by
 * the screens and the CSV export so the names match what staff know.
 */
final class PartnerFields
{
    public const LABELS = [
        'code' => '取引先コード',
        'parent_company' => '親会社/グループ会社',
        'name' => '取引先名',
        'name_kana' => '取引先名(ふりがな)',
        'short_name' => '略名',
        'registration_number' => '登録番号',
        'parent_postal_code' => '郵便番号(親会社)',
        'parent_address' => '住所(親会社)',
        'parent_phone' => 'TEL(親会社)',
        'parent_fax' => 'FAX(親会社)',
        'postal_code' => '郵便番号(得意先)',
        'address' => '住所(得意先)',
        'phone' => 'TEL(得意先)',
        'fax' => 'FAX(得意先)',
        'supplier_same_address' => '仕入先住所も同様',
        'roles' => '取引先カテゴリ',
        'contract_files' => '契約書等添付(得意先用）',
        'supplier_contract_files' => '契約書等添付(仕入用）',
        'customer_status' => '得意先ステータス',
        'customer_classification' => '得意先分類区分',
        'legacy_customer_code' => '旧得意先コード',
        'partner_group' => 'グループ名',
        'segment' => '分類',
        'invoice_recipient' => '請求書送付先',
        'delivery_note_recipient' => '納品書送付先',
        'contact_person' => '担当者',
        'staff' => '自社担当者',
        'contact_note' => '担当者についてのメモ',
        'tax_mode' => '消費税区分',
        'rounding' => '端数区分',
        'tax_calculation_unit' => '計算区分',
        'closing_day' => '請求締日',
        'invoice_issuance' => '請求書発行区分',
        'delivery_note_issuance' => '納品書発行区分',
        'electronic_billing' => '電子請求',
        'collection_month' => '回収予定区分',
        'collection_day' => '回収予定日',
        'collects_by_bill' => '回収区分',
        'bill_threshold_man' => '金額(以上の金額)万',
        'bill_site_days' => 'サイト',
        'notes' => '備考',
        'purchase_category' => '仕入カテゴリ',
        'supplier_tax_mode' => '消費税区分(仕入先)',
        'supplier_rounding' => '端数区分(仕入先)',
        'supplier_tax_calculation_unit' => '計算区分(仕入先)',
        'supplier_contact_person' => '担当者',
        'supplier_postal_code' => '郵便番号(仕入先)',
        'supplier_address' => '住所(仕入先)',
        'supplier_phone' => 'TEL(仕入先)',
        'supplier_fax' => 'FAX(仕入先)',
        'supplier_notes' => '備考',
        'id' => '得意先ID',
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システム得意先ID',
        'mf_partner_id' => 'MF取引先ID',
        'legacy_supplier_code' => '旧得意先コード(仕入)',
    ];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }
}
