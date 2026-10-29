<?php

/*
 * 移行 (roadmap Phase 5): `php artisan legacy:import` takes over the legacy app exports (data_json/<app id>_<name>.json)
 * in the order of the business process (02 → 03 → 01 → …), keeping the legacy record numbers, matching the employees of
 * 02 with the legacy logins, deriving the totals again and reporting what differs; a re-run updates.
 */

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Models\RecordVersion;
use Modules\MasterData\Models\Machine;
use Modules\Orders\Models\Quotation;

/** A legacy record as exported: field code => {type, value}. */
function legacyRecord(int $id, array $values): array
{
    $record = ['$id' => ['type' => '__ID__', 'value' => (string) $id]];
    foreach ($values as $code => $value) {
        $record[$code] = ['type' => is_array($value) && array_is_list($value) && isset($value[0]['value']) ? 'SUBTABLE' : 'X', 'value' => $value];
    }

    return $record;
}

/** Writes an app export into the folder as <app id>_<name>.json. */
function legacyExportFile(string $folder, string $appId, string $name, array $records): void
{
    file_put_contents(sprintf('%s/%04d_%s.json', $folder, (int) $appId, $name), json_encode([
        'app' => ['appId' => $appId, 'name' => $name],
        'exportedAt' => '2026-10-14T01:15:35.694Z',
        'totalCount' => count($records),
        'fields' => [],
        'records' => $records,
    ], JSON_UNESCAPED_UNICODE));
}

beforeEach(function () {
    $this->folder = sys_get_temp_dir().'/legacy-import-'.uniqid();
    mkdir($this->folder);
    $login = ['code' => 'tomy@s-f-k.co.jp', 'name' => '【06】富山　正大'];

    legacyExportFile($this->folder, '7', '02. 従業員マスタ', [
        legacyRecord(5, ['従業員名' => '富山　正大', 'ふりがな' => 'とみやま まさひろ', '作業員コード' => '00005', '在籍状況' => '在籍中', '従業員カテゴリ' => '営業', '作業単価' => '1500']),
        legacyRecord(11, ['従業員名' => '安藤　弘高', '作業員コード' => '00011', '在籍状況' => '退職済', '従業員カテゴリ' => '製造']),
    ]);
    legacyExportFile($this->folder, '8', '03. 機械マスタ', [
        legacyRecord(7, ['機械名' => '7号機', '機械コード' => 'K00007', '加工単価_枚_' => '0.25', '機械単価_H_' => '500', '作成者' => $login, '更新者' => $login,
            '作成日時' => '2024-04-10T10:24:00Z', '更新日時' => '2024-07-30T10:00:00Z']),
    ]);
    legacyExportFile($this->folder, '14', '09. 見積書管理', [
        legacyRecord(3, ['見積書番号' => 'Q-3', '見積日付' => '2025-05-12', '品名' => '長3封筒', '担当者' => [$login], '見積金額_税抜_' => '9999',
            '見積内容' => [['id' => '1', 'value' => ['単価' => ['type' => 'NUMBER', 'value' => '12.5'], '枚数' => ['type' => 'NUMBER', 'value' => '1000']]],
                ['id' => '2', 'value' => ['単価' => ['type' => 'NUMBER', 'value' => ''], '枚数' => ['type' => 'NUMBER', 'value' => '']]]],
            '作成者' => $login, '更新者' => $login, '作成日時' => '2025-05-12T01:00:00Z', '更新日時' => '2025-05-12T02:00:00Z']),
    ]);
});

afterEach(function () {
    array_map('unlink', glob($this->folder.'/*') ?: []);
    @rmdir($this->folder);
});

it('reads the legacy values: trimmed texts, numbers, dates in Japan time, users and table rows', function () {
    $export = LegacyExport::open(glob($this->folder.'/0014_*')[0]);
    $quotation = iterator_to_array($export->records(), false)[0];

    expect($export->appId())->toBe('14')
        ->and($export->totalCount())->toBe(1)
        ->and($quotation->id())->toBe(3)
        ->and($quotation->text('品名'))->toBe('長3封筒')
        ->and($quotation->date('見積日付'))->toBe('2025-05-12')
        ->and($quotation->updatedAt())->toBe('2025-05-12 11:00:00')
        ->and($quotation->users('担当者'))->toBe([['code' => 'tomy@s-f-k.co.jp', 'name' => '【06】富山　正大']])
        ->and($quotation->table('見積内容'))->toHaveCount(2)
        ->and($quotation->table('見積内容')[0]->decimal('単価'))->toBe('12.5')
        ->and($quotation->table('見積内容')[1]->isBlank('単価', '枚数'))->toBeTrue()
        ->and(LegacyUsers::splitName('【02】社長 白石　陽一'))->toBe(['02', '社長', '白石　陽一'])
        ->and(LegacyUsers::normalize('菊池 毅'))->toBe(LegacyUsers::normalize('菊池　毅'));
});

it('takes over the apps in the order of the business process, keeping the legacy numbers, and updates on a re-run', function () {
    expect(Artisan::call('legacy:import', ['--path' => $this->folder]))->toBe(0);

    // 02: the employees, without login (placeholder address) until a legacy login of the same name turns up (03, 09)
    $tomy = User::query()->where('worker_code', '00005')->sole();
    $ando = User::query()->where('worker_code', '00011')->sole();
    expect($tomy->email)->toBe('tomy@s-f-k.co.jp')
        ->and($tomy->sales_staff_code)->toBe('06')
        ->and($tomy->work_rate)->toBe(1500)
        ->and($tomy->employeeCategories->pluck('name')->all())->toBe(['営業'])
        ->and($ando->email)->toBe('00011@'.LegacyUsers::PLACEHOLDER_DOMAIN)
        ->and($ando->employment_status->value)->toBe('resigned')
        ->and($ando->must_change_password)->toBeTrue();

    // 03: 機械マスタID kept, 作成者 / 更新者 / times the legacy ones, 変更履歴 version 1
    $machine = Machine::query()->findOrFail(7);
    expect($machine->legacy_id)->toBe(7)
        ->and($machine->code)->toBe('K00007')
        ->and($machine->processing_rate)->toBe('0.2500')
        ->and($machine->updated_by)->toBe($tomy->id)
        ->and($machine->updated_at?->format('Y-m-d H:i'))->toBe('2024-07-30 19:00')
        ->and(RecordVersion::query()->where('record_type', $machine->getMorphClass())->where('record_id', 7)->count())->toBe(1);

    // 09: the tiers without blank rows, the total derived (the stored one is reported)
    $quotation = Quotation::query()->findOrFail(3);
    expect($quotation->lines)->toHaveCount(1)
        ->and($quotation->total)->toBe(12500)
        ->and($quotation->assignees->pluck('id')->all())->toBe([$tomy->id])
        ->and((string) file_get_contents(storage_path('logs/legacy-import/14.log')))->toContain('見積金額(税抜) 9999 は見積内容から 12500 になりました');

    // a re-run updates
    Artisan::call('legacy:import', ['apps' => ['02', '03', '09'], '--path' => $this->folder]);
    expect(User::query()->where('worker_code', '00005')->count())->toBe(1)
        ->and(Machine::query()->count())->toBe(1)
        ->and(Quotation::query()->sole()->lines)->toHaveCount(1)
        ->and(Artisan::output())->toContain('作成 0 / 更新 1');
});
