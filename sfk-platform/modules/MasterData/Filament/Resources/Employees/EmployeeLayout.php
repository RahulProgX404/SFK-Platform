<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Employees;

use App\Models\User;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Records\RecordSections;
use Modules\MasterData\Domain\Employees\EmployeeFields;

/**
 * The legacy record of 02. 従業員マスタ, row by row with the field widths of the app template (SYSTEM_BRAIN APP_02
 * fields.md; widths from the template ZIP). Users have no 作成者 / 更新者, so the footer row is 従業員ID and the times.
 */
final class EmployeeLayout
{
    /** Rows: [attribute, width px]. */
    private const ROWS = [
        [['name', 252], ['name_kana', 252], ['employee_categories', 196]],
        [['sales_staff_code', 193], ['worker_code', 193], ['department_code', 193], ['employment_status', 322]],
        [['notes', 772]],
    ];

    private const FOOTER = [[['id', 120], ['created_at', 196], ['updated_at', 196]]];

    /** 開発者用フィールド of the legacy app: 作業単価 (and 残業単価, kept with it). */
    private const DEVELOPER = [[['work_rate', 193], ['overtime_rate', 193]]];

    /**
     * @param  callable(string): Component  $make  builds the record entry of an attribute
     * @param  Closure(User|null): bool  $showRates  作業単価 / 残業単価 of the shown employee (EmployeeDataAccess::canViewRates)
     * @return list<Component>
     */
    public static function components(callable $make, Closure $showRates): array
    {
        return [
            self::section(self::ROWS, $make),
            self::section(self::FOOTER, $make),
            Section::make('開発者用フィールド')
                ->collapsed()
                ->schema(RecordSections::rows(self::DEVELOPER, $make))
                ->extraAttributes(['class' => 'kt-record'])
                ->columnSpanFull()
                ->visible(fn (?Model $record): bool => $showRates($record instanceof User ? $record : null)),
        ];
    }

    public static function label(string $attribute): string
    {
        return EmployeeFields::label($attribute);
    }

    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows  rows, a string row is a group label
     * @param  callable(string): ?Component  $make
     */
    private static function section(array $rows, callable $make): Section
    {
        return Section::make()->schema(RecordSections::rows($rows, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
    }
}
