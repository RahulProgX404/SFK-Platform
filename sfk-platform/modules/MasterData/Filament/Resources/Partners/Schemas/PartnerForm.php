<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Partners\Schemas;

use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use LogicException;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Billing\CollectionDay;
use Modules\Core\Domain\Billing\CollectionMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxCalculationUnit;
use Modules\Core\Domain\Tax\TaxMode;
use Modules\MasterData\Domain\Partners\CustomerClassification;
use Modules\MasterData\Domain\Partners\CustomerStatus;
use Modules\MasterData\Domain\Partners\DocumentIssuance;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\MasterData\Domain\Partners\PartnerGroup;
use Modules\MasterData\Domain\Partners\PartnerRole;
use Modules\MasterData\Domain\Partners\PartnerSegment;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Filament\Resources\Partners\PartnerLayout;

/**
 * Create / edit form of 01. 取引先マスタ, laid out like the legacy form (PartnerLayout). Required like the legacy system:
 * 取引先名 (unique), 取引先名(ふりがな), 略名, 取引先カテゴリ, 郵便番号・住所・TEL(得意先), 請求書送付先, 納品書送付先.
 * 取引先コード is numbered on save (Partner) and cannot be typed. `roles` is stored as the is_customer / is_supplier /
 * is_other flags (pages convert it).
 */
final class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        $superAdmin = auth()->user() instanceof User && auth()->user()->isSuperAdmin();

        return $schema
            ->columns(1)
            ->components(PartnerLayout::components(self::input(...), withFooter: false, withDeveloperFields: $superAdmin));
    }

    private static function input(string $name): Component
    {
        $label = PartnerLayout::label($name);

        return match ($name) {
            'code' => TextInput::make($name)->label($label)->disabled()->dehydrated(false),
            'name' => TextInput::make($name)->label($label)->required()->maxLength(64)->unique(ignoreRecord: true),
            'name_kana' => TextInput::make($name)->label($label)->required()->maxLength(128),
            'short_name' => TextInput::make($name)->label($label)->required()->maxLength(64),
            'parent_company' => TextInput::make($name)->label($label)->maxLength(128),
            'registration_number' => TextInput::make($name)->label($label)->placeholder('T1234567890123')->regex('/^T\d{13}$/')->maxLength(14),
            'postal_code' => self::postalCode($name, $label)->required(),
            'parent_postal_code' => self::postalCode($name, $label),
            'supplier_postal_code' => self::postalCode($name, $label)->visible(fn (Get $get): bool => ! $get('supplier_same_address')),
            'address' => TextInput::make($name)->label($label)->required()->maxLength(255),
            'parent_address' => TextInput::make($name)->label($label)->maxLength(255),
            'supplier_address' => TextInput::make($name)->label($label)->maxLength(255)->visible(fn (Get $get): bool => ! $get('supplier_same_address')),
            'phone' => TextInput::make($name)->label($label)->tel()->required()->maxLength(32),
            'parent_phone', 'parent_fax', 'fax' => TextInput::make($name)->label($label)->tel()->maxLength(32),
            'supplier_phone', 'supplier_fax' => TextInput::make($name)->label($label)->tel()->maxLength(32)->visible(fn (Get $get): bool => ! $get('supplier_same_address')),
            'supplier_same_address' => Checkbox::make($name)->label($label)->live()->inline(false),
            'roles' => CheckboxList::make($name)->label($label)->options(PartnerRole::options())->required()->columns(3),
            'contract_files', 'supplier_contract_files' => FileUpload::make($name)
                ->label($label)
                ->multiple()
                ->disk('local')
                ->directory('partners/contracts')
                ->visibility('private')
                ->downloadable()
                ->maxSize(20480),
            'customer_status' => self::select($name, $label, CustomerStatus::options()),
            'customer_classification' => self::select($name, $label, CustomerClassification::options()),
            'partner_group' => self::select($name, $label, PartnerGroup::options()),
            'segment' => self::select($name, $label, PartnerSegment::options()),
            'legacy_customer_code', 'legacy_supplier_code', 'mf_partner_id' => TextInput::make($name)->label($label)->maxLength(64),
            'legacy_id' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(1)->unique(ignoreRecord: true),
            'invoice_recipient', 'delivery_note_recipient' => Radio::make($name)
                ->label($label)
                ->options(DocumentRecipient::options())
                ->default(DocumentRecipient::Customer->value)
                ->required()
                ->inline(),
            'contact_person', 'supplier_contact_person' => TextInput::make($name)->label($label)->maxLength(64),
            'staff' => Select::make($name)
                ->label($label)
                ->relationship('staff', 'name')
                ->multiple()
                ->searchable()
                ->preload(),
            'contact_note', 'notes', 'supplier_notes' => Textarea::make($name)->label($label)->rows(3)->maxLength(4000),
            'tax_mode', 'supplier_tax_mode' => self::select($name, $label, TaxMode::options()),
            'rounding', 'supplier_rounding' => self::select($name, $label, [
                RoundingMode::HalfUp->value => RoundingMode::HalfUp->label(),
                RoundingMode::RoundUp->value => RoundingMode::RoundUp->label(),
                RoundingMode::Truncate->value => RoundingMode::Truncate->label(),
            ]),
            'tax_calculation_unit', 'supplier_tax_calculation_unit' => self::select($name, $label, TaxCalculationUnit::options()),
            'closing_day' => self::select($name, $label, array_combine(
                array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
                array_map(fn (ClosingDay $day): string => $day->value, ClosingDay::cases()),
            )),
            'invoice_issuance', 'delivery_note_issuance' => self::select($name, $label, DocumentIssuance::options()),
            'electronic_billing' => Checkbox::make($name)->label($label)->inline(false),
            'collection_month' => self::select($name, $label, CollectionMonth::options()),
            'collection_day' => self::select($name, $label, CollectionDay::options()),
            'collects_by_bill' => Checkbox::make($name)->label('手形')->inline(false),
            'bill_threshold_man' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->suffix('万円'),
            'bill_site_days' => TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->maxValue(365)->suffix('日'),
            'purchase_category' => self::select($name, $label, PurchaseCategory::options()),
            default => throw new LogicException("No form input for [{$name}]."),
        };
    }

    /** @param  array<string, string>  $options */
    private static function select(string $name, string $label, array $options): Select
    {
        return Select::make($name)->label($label)->options($options)->native(false);
    }

    private static function postalCode(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->placeholder('123-4567')->regex('/^\d{3}-?\d{4}$/')->maxLength(8);
    }
}
