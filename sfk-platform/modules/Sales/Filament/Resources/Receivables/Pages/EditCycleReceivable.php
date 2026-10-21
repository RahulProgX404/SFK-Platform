<?php

declare(strict_types=1);

namespace Modules\Sales\Filament\Resources\Receivables\Pages;

use Modules\Sales\Filament\Resources\Receivables\CycleReceivableResource;

final class EditCycleReceivable extends EditReceivableMonth
{
    protected static string $resource = CycleReceivableResource::class;
}
