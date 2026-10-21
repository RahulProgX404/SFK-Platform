<?php

declare(strict_types=1);

namespace Modules\Purchasing\Filament\Resources\Purchases\Pages;

use App\Models\User;
use Modules\Purchasing\Application\Purchases\OverrunAlert;
use Modules\Purchasing\Filament\Resources\Purchases\PurchaseResource;
use Modules\Purchasing\Models\Purchase;

/** 「仕入原価超過があります。」 to the 通知先 of a purchase saved on the create / edit page (BR-PU-05, OverrunAlert). */
trait SendsOverrunAlert
{
    protected function sendOverrunAlert(Purchase $purchase): void
    {
        $actor = auth()->user();

        app(OverrunAlert::class)->send(
            $purchase,
            $actor instanceof User ? $actor : null,
            PurchaseResource::appName().' '.$purchase->purchase_number,
            PurchaseResource::getUrl('view', ['record' => $purchase]),
        );
    }
}
