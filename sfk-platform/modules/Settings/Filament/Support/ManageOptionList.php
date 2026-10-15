<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Support;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use LogicException;

/** List page of an OptionListResource; new options are appended at the end of the order. */
abstract class ManageOptionList extends ManageRecords
{
    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        if (! is_a($resource, OptionListResource::class, true)) {
            throw new LogicException(static::class.' must belong to an OptionListResource.');
        }

        return [
            CreateAction::make()
                ->label($resource::optionLabel().'を追加')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => $resource::canManage())
                ->using(fn (array $data) => $resource::createOption($data)),
        ];
    }
}
