<?php

declare(strict_types=1);

namespace Modules\MasterData\Application\Legacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Application\Users\UserAccountService;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Models\EmployeeCategory;

/**
 * Takes over 02. 従業員マスタ (legacy app 7, SYSTEM_BRAIN APP_02) — the first step of the import: employees are the
 * users other apps refer to (作業者, 担当者, 作成者 …).
 *
 * An employee is the user with its 作業員コード, else the user of the same name (e.g. created from a legacy login by
 * the 取引先マスタ import), else a new employee. The legacy app holds no emails: a new employee gets
 * <作業員コード>@employees.invalid until a legacy login of the same name turns up in a later app (LegacyUsers), and a
 * random temporary password, so nobody signs in before an administrator issues a password. 従業員ID is not kept (the
 * employees are users with their own ids); the users found again by 作業員コード on a re-run.
 */
final class LegacyEmployeeImport implements LegacyImporter
{
    private const STATUSES = ['在籍中' => EmploymentStatus::Active, '休職中' => EmploymentStatus::OnLeave, '退職済' => EmploymentStatus::Resigned];

    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '7';
    }

    public function label(): string
    {
        return '02. 従業員マスタ';
    }

    public function step(): int
    {
        return 10;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $categories = EmployeeCategory::query()->pluck('id', 'name')->all();

        DB::transaction(function () use ($export, $log, $categories): void {
            foreach ($export->records() as $record) {
                $label = '従業員ID '.$record->id();
                $name = $record->text('従業員名');
                $workerCode = $record->text('作業員コード');
                if ($name === null) {
                    $log->skipped("{$label}: 従業員名がありません");

                    continue;
                }

                $user = ($workerCode !== null ? User::query()->where('worker_code', $workerCode)->first() : null) ?? $this->users->named($name);
                $attributes = $this->attributes($record, $name, $workerCode, $user, $label, $log);

                if ($user === null) {
                    $user = $this->accounts->create([...$attributes, 'email' => $this->placeholderEmail($workerCode, $record)])->user;
                    $log->created();
                } else {
                    $user->fill($attributes)->save();
                    $log->updated();
                }

                $user->forceFill(array_filter([
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]))->saveQuietly();

                $category = $record->text('従業員カテゴリ');
                if ($category !== null) {
                    isset($categories[$category])
                        ? $user->employeeCategories()->sync([$categories[$category]])
                        : $log->warn("{$label}: 従業員カテゴリ「{$category}」はありません");
                }
            }
        });

        $this->users->forget();
    }

    /** @return array<string, mixed> users columns */
    private function attributes(LegacyRecord $record, string $name, ?string $workerCode, ?User $user, string $label, LegacyImportLog $log): array
    {
        [$lastName, $firstName] = self::split($name);
        [$lastKana, $firstKana] = self::split((string) $record->text('ふりがな'));

        $status = $record->text('在籍状況');
        if ($status !== null && ! isset(self::STATUSES[$status])) {
            $log->warn("{$label}: 在籍状況「{$status}」は選択肢にないため在籍中にしました");
        }

        // 担当者コード: kept when no other employee has it (the legacy app filled it for 3 of them)
        $salesCode = $record->text('担当者コード');
        if ($salesCode !== null && User::query()->where('sales_staff_code', $salesCode)->when($user !== null, fn ($query) => $query->whereKeyNot($user?->id))->exists()) {
            $log->warn("{$label}: 担当者コード「{$salesCode}」は他の従業員が使っているため空欄にしました");
            $salesCode = null;
        }

        return array_filter([
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'last_name_kana' => $lastKana,
            'first_name_kana' => $firstKana,
            'worker_code' => $workerCode,
            'sales_staff_code' => $salesCode ?? $user?->sales_staff_code,
            'department_code' => $record->text('部課コード'),
            'employment_status' => (self::STATUSES[$status ?? ''] ?? EmploymentStatus::Active)->value,
            'work_rate' => $record->int('作業単価'),
            'overtime_rate' => $record->int('残業単価'),
            'notes' => $record->text('備考'),
        ], fn (mixed $value): bool => $value !== null);
    }

    private function placeholderEmail(?string $workerCode, LegacyRecord $record): string
    {
        $local = $workerCode !== null && preg_match('/^[A-Za-z0-9._-]+$/', $workerCode) === 1 ? $workerCode : 'employee-'.$record->id();
        $email = $local.'@'.LegacyUsers::PLACEHOLDER_DOMAIN;

        return User::query()->where('email', $email)->exists() ? 'employee-'.$record->id().'@'.LegacyUsers::PLACEHOLDER_DOMAIN : $email;
    }

    /**
     * 「白石　博一」 → ['白石', '博一']; one word → [word, null].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private static function split(string $name): array
    {
        $parts = preg_split('/[\s\x{3000}]+/u', trim($name), 2) ?: [];

        return [LegacyRecord::trim($parts[0] ?? null), LegacyRecord::trim($parts[1] ?? null)];
    }
}
