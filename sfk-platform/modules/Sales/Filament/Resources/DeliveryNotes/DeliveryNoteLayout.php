<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\DeliveryNotes;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Records\RecordSections;
use Modules\Sales\Domain\Documents\DocumentFields;
use Modules\Sales\Filament\Resources\Documents\DocumentParts;
use Modules\Sales\Models\DeliveryNote;

/**
 * Form and record page of 10. 納品書管理(売上) (SYSTEM_BRAIN APP_10 fields.md "Form layout", widths from the template):
 * 送付 / 登録番号 / 納品書番号, 売上日付 / 得意先名 / 担当者 / 請求予定日, 郵便番号 / 納品先 / 発行区分, 納品金額合計(税抜), the
 * table 納品内容詳細, the subtotals and taxes per rate, 備考 and 納品書PDF. The legacy 税率 header select (unused by the
 * formulas) is left out. Notes are created from 06. 売上入力; 郵便番号 / 納品先 can be corrected here (updated_delivery_address
 * of the legacy JS).
 */
final class DeliveryNoteLayout
{
    private const MAIN = [
        [['send_status', 265], [null, 852], ['registration_number', 193], ['number', 192]],
        [['sold_on', 117], ['customer_id', 449], ['assignees', 344], [null, 301], ['billing_due_on', 117]],
        [['postal_code', 193], ['address', 502], ['delivery_note_issuance', 193], ['invoice_issuance', 193]],
        [['subtotal', 193]],
        [['lines', 1500]],
        [['standard_subtotal', 193], ['reduced_subtotal', 193], ['exempt_subtotal', 193], ['out_of_scope_subtotal', 193]],
        [['standard_tax', 193], ['reduced_tax', 193], ['exempt_tax', 193], ['out_of_scope_tax', 193], ['tax_total', 193]],
        [['notes', 840]],
        [['pdf_files', 207]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['customer_code', 193], ['closing_day', 193], ['customer_rounding', 193], ['legacy_id', 193]]];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(
            fn (string $name): ?Component => match ($name) {
                'sold_on', 'billing_due_on' => DatePicker::make($name)->label(self::label($name))
                    ->default(fn (): ?string => $name === 'sold_on' ? now()->toDateString() : null),
                'closing_day' => TextEntry::make($name)->label(self::label($name))->placeholder('')
                    ->state(fn (?DeliveryNote $record): ?string => $record?->closing_day),
                default => DocumentParts::input($name, DocumentFields::DELIVERY_NOTE, invoice: false),
            },
            withFooter: false,
        ));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::components(
            fn (string $name): Component => DocumentParts::entry($name, DocumentFields::DELIVERY_NOTE, invoice: false)
                ?? TextEntry::make($name)->label(self::label($name))->placeholder('—'),
            withFooter: true,
        ));
    }

    public static function label(string $attribute): string
    {
        return DocumentFields::DELIVERY_NOTE[$attribute];
    }

    /**
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    private static function components(callable $make, bool $withFooter): array
    {
        $components = [RecordSections::section(self::MAIN, $make)];
        if ($withFooter) {
            $components[] = RecordSections::section(self::FOOTER, $make);
        }
        if (auth()->user() instanceof User && auth()->user()->isSuperAdmin()) {
            $components[] = RecordSections::section(self::DEVELOPER, $make, '開発者用フィールド', collapsed: true);
        }

        return $components;
    }
}
