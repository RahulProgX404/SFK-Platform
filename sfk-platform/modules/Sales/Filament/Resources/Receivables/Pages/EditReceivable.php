<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Sales\Filament\Resources\Receivables\ReceivableResource;

final class EditReceivable extends EditReceivableMonth
{
    protected static string $resource = ReceivableResource::class;
}
