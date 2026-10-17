<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * 仕入カテゴリ — accounting purchase category of a supplier (SYSTEM_BRAIN APP_01: the template's 41 options, plus
 * 42.雑包装費 added later in the live legacy app).
 * 「(否インボス)」 = supplier without qualified-invoice registration.
 */
enum PurchaseCategory: string
{
    use HasOptions;

    case C01 = '01.商品仕入';
    case C02 = '02.商品仕入(否インボス)';
    case C03 = '03.材料仕入';
    case C04 = '04.材料仕入(否インボス)';
    case C05 = '05.外注加工';
    case C06 = '06.外注加工(否インボス)';
    case C07 = '07.原修繕費';
    case C08 = '08.原修繕費(否インボス)';
    case C09 = '09.原消耗品';
    case C10 = '10.原消耗品(否インボス)';
    case C11 = '11.原包装費';
    case C12 = '12.原包装費(否インボス)';
    case C13 = '13.原運送代';
    case C14 = '14.原運送代(否インボス)';
    case C15 = '15.原雑費';
    case C16 = '16.原雑費(否インボス)';
    case C17 = '17.消耗品';
    case C18 = '18.消耗品(否インボス)';
    case C19 = '19.修繕費';
    case C20 = '20.修繕費(否インボス)';
    case C21 = '21.事務消耗品';
    case C22 = '22.事務消耗品(否インボス)';
    case C23 = '23.運送代';
    case C24 = '24.配送';
    case C25 = '25.配送(否インボス)';
    case C26 = '26.雑費';
    case C27 = '27.雑費(否インボス)';
    case C28 = '28.包装費';
    case C29 = '29.包装費(否インボス)';
    case C30 = '30.経費';
    case C31 = '31.経費(否インボス)';
    case C32 = '32.雑雑費';
    case C33 = '33.雑原修繕費';
    case C34 = '34.雑修繕費';
    case C35 = '35.雑原消耗品';
    case C36 = '36.雑消耗品';
    case C37 = '37.雑材料仕入';
    case C38 = '38.雑商品仕入';
    case C39 = '39.雑運送費';
    case C40 = '40.雑外注';
    case C41 = '41.雑経費';
    case C42 = '42.雑包装費';

    /** Supplier without qualified-invoice (インボイス) registration. */
    public function isWithoutInvoiceRegistration(): bool
    {
        return str_contains($this->value, '(否インボス)');
    }
}
