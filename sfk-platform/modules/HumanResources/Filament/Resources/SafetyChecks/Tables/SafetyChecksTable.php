<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\SafetyChecks\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Domain\Filters\DateMode;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Support\AppFormat;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckLayout;
use Modules\HumanResources\Filament\Resources\SafetyChecks\SafetyCheckResource;
use Modules\HumanResources\Filament\Support\Employees;
use Modules\HumanResources\Models\SafetyCheck;
use Modules\HumanResources\Models\SafetyCheckItem;

/**
 * Record list of 17. 安全衛生チェック with the legacy views (SYSTEM_BRAIN APP_17 views.md): 一覧 (newest first) and 一覧
 * （先月分） (日付 in the last month, by 部署・機械); the rows of チェックリスト are shown as 「No 項目: 評価」. インライン編集
 * changes 部署・機械 and 日付.
 */
final class SafetyChecksTable
{
    public const VIEW_LIST = 'list';

    public const LIST_COLUMNS = ['checked_on', 'department', 'user_id', 'employee_id', 'items'];

    private const USERS = ['user_id' => 'user', 'employee_id' => 'employee'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'department_machine', 'user_id', 'checked_on', 'employee_id', 'department', 'items', 'created_at'] as $name) {
            $columns[$name] = SafetyCheckLayout::label($name);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [
            ListView::table(self::VIEW_LIST, '一覧', self::LIST_COLUMNS, [['field' => 'id', 'direction' => 'desc']]),
            ListView::table('last-month', '一覧 （先月分）', self::LIST_COLUMNS, [['field' => 'department_machine', 'direction' => 'desc']],
                [['field' => 'checked_on', 'operator' => FilterOperator::Equals->value, 'values' => [], 'mode' => DateMode::LastMonth->value]]),
        ];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : self::LIST_COLUMNS;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'employee', 'items']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(SafetyCheckResource::class))
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('安全衛生チェックがありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_17 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => SafetyCheckLayout::label($name);

        return [
            FilterField::text('department_machine', $label('department_machine')),
            FilterField::choice('department', $label('department'), HrChoices::options(HrChoices::DEPARTMENTS)),
            FilterField::user('user_id', $label('user_id')),
            FilterField::user('employee_id', $label('employee_id')),
            FilterField::date('checked_on', $label('checked_on')),
            FilterField::choice('answer', $label('answer'), HrChoices::options(HrChoices::ANSWERS))->relationship('items', 'answer'),
            FilterField::text('item', $label('item'))->relationship('items', 'item'),
            FilterField::number('id', $label('id')),
            FilterField::user('created_by', $label('creator')),
            FilterField::dateTime('created_at', $label('created_at')),
            FilterField::user('updated_by', $label('updater')),
            FilterField::dateTime('updated_at', $label('updated_at')),
        ];
    }

    /** @return list<InlineField> */
    public static function inlineFields(): array
    {
        return [InlineField::text('department_machine')->maxLength(255), InlineField::date('checked_on')->required()];
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(SafetyCheckLayout::label($name))->placeholder('');
        $user = fn (string $key): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            User::query()->select('users.name')->whereColumn('users.id', "safety_checks.{$key}"),
            $direction,
        );

        return match (true) {
            $name === 'department_machine' => $column->searchable(),
            $name === 'checked_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            isset(self::USERS[$name]) => $column->state(Employees::popup(self::USERS[$name]))->html()->disabledClick()->sortable(query: $user($name)),
            $name === 'items' => $column
                ->state(fn (SafetyCheck $record): array => $record->items
                    ->map(fn (SafetyCheckItem $row): string => trim(AppFormat::number($row->number).' '.mb_strimwidth((string) $row->item, 0, 40, '…').': '.$row->answer))
                    ->all())
                ->listWithLineBreaks()
                ->limitList(5)
                ->expandableLimitedList()
                // by the number of rows answered いいえ
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                    SafetyCheckItem::query()->selectRaw('count(*)')->whereColumn('safety_check_items.safety_check_id', 'safety_checks.id')->where('answer', 'いいえ'),
                    $direction,
                )),
            default => $column,
        };
    }
}
