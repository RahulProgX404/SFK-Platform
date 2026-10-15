<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Partners;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\MasterData\Domain\Partners\PartnerFields;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Models\Partner;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 「ファイルに書き出す」 of 01. 取引先マスタ (legacy ファイル書き出し): the listed partners as CSV with the legacy field
 * names as headers, UTF-8 with BOM so Excel opens it correctly. Gated by the app's ファイル書き出し permission.
 */
final class PartnerCsvExport
{
    /** Exported attributes, in the legacy field order. */
    private const COLUMNS = [
        'id', 'code', 'parent_company', 'name', 'name_kana', 'short_name', 'registration_number', 'roles',
        'parent_postal_code', 'parent_address', 'parent_phone', 'parent_fax',
        'postal_code', 'address', 'phone', 'fax', 'supplier_same_address',
        'customer_status', 'customer_classification', 'legacy_customer_code', 'partner_group', 'segment',
        'invoice_recipient', 'delivery_note_recipient', 'contact_person', 'staff', 'contact_note',
        'tax_mode', 'rounding', 'tax_calculation_unit', 'closing_day', 'invoice_issuance', 'delivery_note_issuance',
        'electronic_billing', 'collection_month', 'collection_day', 'collects_by_bill', 'bill_threshold_man',
        'bill_site_days', 'notes',
        'purchase_category', 'supplier_tax_mode', 'supplier_rounding', 'supplier_tax_calculation_unit',
        'supplier_contact_person', 'supplier_postal_code', 'supplier_address', 'supplier_phone', 'supplier_fax',
        'supplier_notes', 'created_at', 'updated_at',
    ];

    /** @param  Builder<Partner>  $query */
    public function download(Builder $query): StreamedResponse
    {
        $fileName = '取引先マスタ_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}");
            fputcsv($out, array_map(fn (string $column): string => PartnerFields::label($column), self::COLUMNS), escape: '');

            $query->with('staff')->chunk(500, function ($partners) use ($out): void {
                foreach ($partners as $partner) {
                    fputcsv($out, $this->row($partner), escape: '');
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    public function row(Partner $partner): array
    {
        return array_map(fn (string $column): string => match ($column) {
            'roles' => implode(',', array_map(fn (PartnerRole $role): string => $role->value, $partner->roles())),
            'staff' => $partner->staff->pluck('name')->implode(','),
            'supplier_same_address', 'electronic_billing' => $partner->getAttribute($column) ? '✓' : '',
            'collects_by_bill' => $partner->getAttribute($column) ? '手形' : '',
            default => self::scalar($partner->getAttribute($column)),
        }, self::COLUMNS);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Carbon => $value->format('Y-m-d H:i'),
            default => (string) $value,
        };
    }
}
