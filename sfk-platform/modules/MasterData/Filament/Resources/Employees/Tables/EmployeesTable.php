<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees\Tables;

use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\EmployeeDataAccess;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Models\EmployeeCategory;
use Modules\MasterData\Domain\Employees\EmployeeFields;
use Modules\MasterData\Filament\Resources\Employees\EmployeeLayout;
use Modules\MasterData\Filament\Resources\Employees\EmployeeResource;

/**
 * Record list of 02. 従業員マスタ. The legacy view 「在籍中の従業員一覧」 (SYSTEM_BRAIN APP_02 views.md: 在籍状況 in
 * 在籍中, newest 従業員ID first) is the default view, （すべて） shows columns() in order. The search box replaces the
 * 「従業員一覧(検索特化)」 plugin view. 作業単価 / 残業単価 are columns and filter / sort fields for administrators
 * only (EmployeeDataAccess), so a list never shows or orders by them for anyone else.
 */
final class EmployeesTable
{
    public const VIEW_ACTIVE = 'active';

    /** Columns of the legacy view 在籍中の従業員一覧, in order. */
    public const LIST_COLUMNS = ['employee_categories', 'name', 'name_kana', 'sales_staff_code', 'worker_code', 'department_code', 'employment_status'];

    /** ふりがな: 姓 + 名 (フリガナ) of the user, like UserPresenter of ユーザー管理. */
    private const NAME_KANA_SQL = "trim(coalesce(users.last_name_kana, '') || ' ' || coalesce(users.first_name_kana, ''))";

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $names = ['id', 'name', 'employee_categories', 'name_kana', 'sales_staff_code', 'worker_code'];
        if (self::showsRates()) {
            $names = [...$names, ...EmployeeFields::RATES];
        }

        $columns = [];
        foreach ([...$names, 'department_code', 'notes', 'employment_status'] as $name) {
            $columns[$name] = EmployeeLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(
            self::VIEW_ACTIVE,
            '在籍中の従業員一覧',
            self::LIST_COLUMNS,
            [['field' => 'id', 'direction' => 'desc']],
            [['field' => 'employment_status', 'operator' => FilterOperator::In->value, 'values' => [EmploymentStatus::Active->value]]],
        )];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->columns([
                IconColumn::make('record')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(EmployeeResource::class))
            ->emptyStateIcon(Heroicon::OutlinedIdentification)
            ->emptyStateHeading('従業員がありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_02 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => EmployeeLayout::label($name);

        return [
            FilterField::text('name', $label('name')),
            FilterField::text('name_kana', $label('name_kana'))->query(self::filterNameKana(...)),
            FilterField::choice('employee_categories', $label('employee_categories'), fn (): array => EmployeeCategory::query()
                ->ordered()
                ->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])
                ->all())
                ->relationship('employeeCategories', 'id'),
            FilterField::text('sales_staff_code', $label('sales_staff_code')),
            FilterField::text('worker_code', $label('worker_code')),
            FilterField::text('department_code', $label('department_code')),
            FilterField::choice('employment_status', $label('employment_status'), EmploymentStatus::options()),
            FilterField::textarea('notes', $label('notes')),
            FilterField::number('id', $label('id')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::dateTime('updated_at', $label('updated_at')),
            ...(self::showsRates()
                ? array_map(fn (string $rate): FilterField => FilterField::number($rate, $label($rate)), EmployeeFields::RATES)
                : []),
        ];
    }

    public static function nameKana(User $user): string
    {
        return trim(($user->getAttribute('last_name_kana') ?? '').' '.($user->getAttribute('first_name_kana') ?? ''));
    }

    /** 従業員カテゴリ (several allowed), in the order of the option list. */
    public static function categoryNames(User $user): string
    {
        return $user->employeeCategories->pluck('name')->implode('、');
    }

    private static function showsRates(): bool
    {
        return EmployeeDataAccess::isAdmin(EmployeeResource::actor());
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)
            ->label(EmployeeLayout::label($name))
            ->placeholder('');

        return match (true) {
            $name === 'name', $name === 'sales_staff_code', $name === 'worker_code', $name === 'department_code' => $column->searchable()->sortable(),
            $name === 'name_kana' => $column
                ->state(fn (User $record): string => self::nameKana($record))
                ->searchable(['last_name_kana', 'first_name_kana'])
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy('last_name_kana', $direction)
                    ->orderBy('first_name_kana', $direction)),
            $name === 'employee_categories' => $column
                ->state(fn (User $record): string => self::categoryNames($record))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    EmployeeCategory::query()
                        ->selectRaw('min(employee_categories.sort_order)')
                        ->join('employee_category_user', 'employee_category_user.employee_category_id', '=', 'employee_categories.id')
                        ->whereColumn('employee_category_user.user_id', 'users.id'),
                    $direction,
                )),
            $name === 'employment_status' => $column->formatStateUsing(fn (mixed $state): string => $state instanceof EmploymentStatus ? $state->label() : ''),
            in_array($name, EmployeeFields::RATES, true) => $column
                ->state(fn (User $record): mixed => $record->getAttribute($name))
                ->alignEnd()
                ->formatStateUsing(fn (mixed $state): string => EmployeeFields::formatRate($state)),
            default => $column,
        };
    }

    /**
     * ふりがな is two columns: compares the joined 姓 + 名 like a text field (keywords ignore case).
     *
     * @param  Builder<*>  $query
     */
    private static function filterNameKana(Builder $query, FilterCondition $condition): void
    {
        $kana = self::NAME_KANA_SQL;
        $value = $condition->values[0] ?? '';

        match ($condition->operator) {
            FilterOperator::Contains => $query->whereRaw("lower({$kana}) like ?", ['%'.mb_strtolower($value).'%']),
            FilterOperator::NotContains => $query->whereRaw("lower({$kana}) not like ?", ['%'.mb_strtolower($value).'%']),
            FilterOperator::Equals => $query->whereRaw("{$kana} = ?", [$value]),
            FilterOperator::NotEquals => $query->whereRaw("{$kana} <> ?", [$value]),
            FilterOperator::OneOf => $query->whereIn(DB::raw($kana), $condition->values),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
