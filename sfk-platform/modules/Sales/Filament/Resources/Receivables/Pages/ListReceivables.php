<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;

final class ListReceivables extends ListReceivableMonths
{
    protected static string $resource = ReceivableResource::class;
}
