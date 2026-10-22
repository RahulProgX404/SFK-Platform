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
 * Form and record page of 10. 納品書管理(売上) (fields of SYSTEM_BRAIN APP_10 fields.md), grouped by task on a 12-column
 * grid of the 1,500 px canvas: 基本情報 (納品書番号, 売上日付, 請求予定日, 送付, 登録番号; 得意先名, 担当者, 納品金額合計), 納品先
 * (郵便番号 / 納品先 with the issuance categories), 納品内容 (the table 納品内容詳細), 税率別内訳 and その他 (備考, 納品書PDF).
 * The legacy 税率 header select (unused by the formulas) is left out. Notes are created from 06. 売上入力; 郵便番号 /
 * 納品先 can be corrected here (updated_delivery_address of the legacy JS).
 */
final class DeliveryNoteLayout
{
    private const MAIN = [
        '基本情報',
        [['number', 250], ['sold_on', 250], ['billing_due_on', 250], ['send_status', 375], ['registration_number', 375]],
        [['customer_id', 750], ['assignees', 375], ['subtotal', 375]],
        '納品先',
        [['postal_code', 250], ['address', 750], ['delivery_note_issuance', 250], ['invoice_issuance', 250]],
        '納品内容',
        [['lines', 1500]],
        '税率別内訳',
        [['standard_subtotal', 250], ['reduced_subtotal', 250], ['exempt_subtotal', 250], ['out_of_scope_subtotal', 250]],
        [['standard_tax', 250], ['reduced_tax', 250], ['exempt_tax', 250], ['out_of_scope_tax', 250], ['tax_total', 250]],
        'その他',
        [['notes', 1000], ['pdf_files', 500]],
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
