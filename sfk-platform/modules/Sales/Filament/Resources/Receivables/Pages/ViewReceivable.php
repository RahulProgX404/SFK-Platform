<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;

final class ViewReceivable extends ViewReceivableMonth
{
    protected static string $resource = ReceivableResource::class;
}
