<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\EmployeeCategories\Pages;

use Modules\Settings\Filament\Resources\EmployeeCategories\EmployeeCategoryResource;
use Modules\Settings\Filament\Support\ManageOptionList;

final class ManageEmployeeCategories extends ManageOptionList
{
    protected static string $resource = EmployeeCategoryResource::class;

    protected static ?string $title = '従業員カテゴリ';
}
