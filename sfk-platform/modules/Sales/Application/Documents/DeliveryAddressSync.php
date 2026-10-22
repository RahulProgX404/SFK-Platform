<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Documents;

use Modules\Sales\Models\Sale;

/**
 * AutoReflectSalesDeliveryAddress.bundle.js (06. 売上入力, on 保存 of an edited sale, administrators): the address the
 * sale's 納品書 goes to (郵便番号(P) / 住所(P), Sale::deliveryAddress()) is written to its delivery note when it differs.
 * As in the legacy script a note without an address is left alone; unlike it, the note is the sale's own (the legacy
 * took the first note of the customer) and the saved values are used (the legacy read the record before the edit).
 * A blank sale address does not clear the note's.
 */
final class DeliveryAddressSync
{
    /** @return bool whether the note was updated */
    public function reflect(Sale $sale): bool
    {
        $note = $sale->deliveryNote;
        $address = $sale->deliveryAddress();

        if ($note === null || blank($note->address) || blank($address['address']) || $note->address === $address['address']) {
            return false;
        }

        $note->update(['postal_code' => $address['postal_code'], 'address' => $address['address']]);

        return true;
    }
}
