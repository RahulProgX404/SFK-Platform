<?php

declare(strict_types=1);

namespace Modules\Analytics\Filament\Resources\SalesPlans\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Modules\Analytics\Application\SalesPlanActuals;
use Modules\Analytics\Filament\Resources\SalesPlans\SalesPlanResource;
use Modules\Analytics\Filament\Resources\SalesPlans\Tables\SalesPlansTable;
use Modules\Analytics\Models\SalesPlan;
use Modules\Core\Filament\AppPages\AppListRecords;

/**
 * List of 25. 販売先別売上計画. 「…」 has 売上実績を反映 for administrators: the monthly actuals of the plans in the list are
 * taken from the customers' sales of 06. 売上入力 (SalesPlanActuals).
 */
final class ListSalesPlans extends AppListRecords
{
    protected static string $resource = SalesPlanResource::class;

    public function listColumns(): array
    {
        return SalesPlansTable::columns();
    }

    /** The legacy views (SYSTEM_BRAIN APP_25 views.md). */
    public function defaultListViews(): array
    {
        return SalesPlansTable::defaultViews();
    }

    public function filterFields(): array
    {
        return SalesPlansTable::filterFields();
    }

    public function inlineFields(): array
    {
        return SalesPlansTable::inlineFields();
    }

    protected function optionActions(): array
    {
        return [
            Action::make('fillActuals')
                ->label('売上実績を反映')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->user_type->isAdministrator() && SalesPlanResource::canCreate())
                ->requiresConfirmation()
                ->modalHeading('一覧の売上計画に売上入力の実績を反映しますか？')
                ->modalSubmitActionLabel('反映する')
                ->modalCancelActionLabel('キャンセル')
                ->action(function (): void {
                    $query = $this->getFilteredTableQuery();
                    $plans = $query instanceof Builder ? $query->clone()->reorder()->get()->whereInstanceOf(SalesPlan::class) : collect();
                    $count = app(SalesPlanActuals::class)->fill($plans);

                    Notification::make()->success()->title('売上実績を反映しました（'.$count.'件）')->send();
                }),
        ];
    }
}
