<?php

declare(strict_types=1);

namespace Modules\HumanResources\Filament\Resources\AlcoholChecks\Tables;

use App\Models\User;
use Closure;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Tables\AppRecordActions;
use Modules\Core\Filament\Tables\AppTextColumn;
use Modules\Core\Filament\Tables\InlineField;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckLayout;
use Modules\HumanResources\Filament\Resources\AlcoholChecks\AlcoholCheckResource;
use Modules\HumanResources\Filament\Support\Employees;

/**
 * Record list of 18. アルコールチェック with the legacy view 一覧 (SYSTEM_BRAIN APP_18 views.md: both checks, newest first);
 * the 運転後 columns are named 「…(運転後)」. （すべて） shows columns() in order. インライン編集 changes その他必要な項目.
 */
final class AlcoholChecksTable
{
    public const VIEW_LIST = 'list';

    private const BLOCK = ['_time', '_vehicle', '_method', '_detected', '_checker_id', '_instruction', '_notes'];

    /**
     * Every column (name => label), in the order of the legacy （すべて）.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [];
        foreach (['id', 'driver_id', 'before_vehicle', 'before_method', 'before_detected', 'before_checker_id', 'before_instruction', 'checked_on', 'before_time',
            'before_notes', ...self::after(), 'employee_id', 'created_at'] as $name) {
            $columns[$name] = AlcoholCheckLayout::label($name, qualified: true);
        }

        return $columns;
    }

    /** @return list<ListView> */
    public static function defaultViews(): array
    {
        return [ListView::table(self::VIEW_LIST, '一覧', ['driver_id', 'checked_on', 'before_time', 'before_vehicle', 'before_method', 'before_detected', 'before_checker_id',
            'before_instruction', 'before_notes', ...self::after()], [['field' => 'id', 'direction' => 'desc']])];
    }

    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $names = $livewire instanceof AppListRecords ? $livewire->viewColumns() : array_keys(self::columns());

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['driver', 'employee', 'beforeChecker', 'afterChecker']))
            ->columns([
                IconColumn::make('record')->label('')->state(true)->icon(Heroicon::OutlinedDocumentText)->color('primary')->width('2.5rem'),
                ...array_map(fn (string $name): AppTextColumn => self::column($name), $names),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions(AppRecordActions::make(AlcoholCheckResource::class))
            ->emptyStateIcon(Heroicon::OutlinedBeaker)
            ->emptyStateHeading('アルコールチェックがありません');
    }

    /**
     * Fields of the 絞り込む dialog, in the order of the legacy form (SYSTEM_BRAIN APP_18 fields.md).
     *
     * @return list<FilterField>
     */
    public static function filterFields(): array
    {
        $label = fn (string $name): string => AlcoholCheckLayout::label($name, qualified: true);
        $block = fn (string $prefix): array => [
            FilterField::choice($prefix.'_vehicle', $label($prefix.'_vehicle'), HrChoices::options(HrChoices::VEHICLES)),
            FilterField::choice($prefix.'_method', $label($prefix.'_method'), HrChoices::options(HrChoices::METHODS)),
            FilterField::choice($prefix.'_detected', $label($prefix.'_detected'), HrChoices::options(HrChoices::DETECTED)),
            FilterField::user($prefix.'_checker_id', $label($prefix.'_checker_id')),
            FilterField::text($prefix.'_instruction', $label($prefix.'_instruction')),
            FilterField::textarea($prefix.'_notes', $label($prefix.'_notes')),
        ];

        return [
            FilterField::date('checked_on', $label('checked_on')),
            FilterField::user('driver_id', $label('driver_id')),
            FilterField::user('employee_id', $label('employee_id')),
            ...$block('before'),
            ...$block('after'),
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
        return [InlineField::textarea('before_notes'), InlineField::textarea('after_notes')];
    }

    /** @return list<string> the 運転後 columns */
    private static function after(): array
    {
        return array_map(fn (string $field): string => 'after'.$field, self::BLOCK);
    }

    private static function column(string $name): AppTextColumn
    {
        $column = AppTextColumn::make($name)->label(AlcoholCheckLayout::label($name, qualified: true))->placeholder('');
        $user = fn (string $key): Closure => fn (Builder $query, string $direction): Builder => $query->orderBy(
            User::query()->select('users.name')->whereColumn('users.id', "alcohol_checks.{$key}"),
            $direction,
        );

        return match (true) {
            $name === 'checked_on' => $column->date('Y-m-d'),
            $name === 'created_at' => $column->dateTime('Y-m-d H:i'),
            str_ends_with($name, '_time') => $column->formatStateUsing(fn (mixed $state): string => is_string($state) ? substr($state, 0, 5) : ''),
            str_ends_with($name, '_notes') => $column->searchable()->limit(60),
            // user names open the ユーザー情報 popup, so the cell is not a link to the record
            isset(AlcoholCheckLayout::USERS[$name]) => $column->state(Employees::popup(AlcoholCheckLayout::USERS[$name]))->html()->disabledClick()->sortable(query: $user($name)),
            default => $column,
        };
    }
}
