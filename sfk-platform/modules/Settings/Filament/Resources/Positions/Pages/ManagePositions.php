<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Positions\Pages;

use Modules\Settings\Filament\Resources\Positions\PositionResource;
use Modules\Settings\Filament\Support\ManageOptionList;

final class ManagePositions extends ManageOptionList
{
    protected static string $resource = PositionResource::class;

    protected static ?string $title = '役職';
}
