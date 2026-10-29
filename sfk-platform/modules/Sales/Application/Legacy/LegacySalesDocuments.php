<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\DeliveryNote;
use Modules\Sales\Models\Invoice;

/**
 * Documents of the legacy apps 10. 納品書管理(売上) and 11. 請求書管理(売上) (SYSTEM_BRAIN APP_10 / APP_11, BP-06): the
 * record number and the document number kept, the customer by its 得意先ID, 担当者, 送付 and the rows as printed
 * (snapshots, BR-SA-15) — each row linked to its sale by 売上No (06 taken over before), and the sale to the document.
 * The totals are derived from the rows (tax per rate on the document, BR-TX-04); differences from the stored ones are
 * reported. The PDFs are not in the export (reported). The number sequence continues after the highest number.
 */
abstract class LegacySalesDocuments implements LegacyImporter
{
    /** @var array<string, int> 売上No => sale id */
    private array $sales = [];

    public function __construct(
        protected readonly LegacyRows $rows,
        protected readonly LegacyUsers $users,
        protected readonly LegacyPartners $partners,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    /** @return class-string<DeliveryNote|Invoice> */
    abstract protected function model(): string;

    abstract protected function table(): string;

    abstract protected function linesTable(): string;

    /** The 'id' column of the rows (delivery_note_id / invoice_id), also the sales' link to the document. */
    abstract protected function foreignKey(): string;

    abstract protected function idLabel(): string;

    /** @return array<string, mixed> the document's columns */
    abstract protected function attributes(LegacyRecord $record, int $customerId, string $label, LegacyImportLog $log): array;

    /** @return list<array<string, mixed>> the rows (without the document's id) */
    abstract protected function lines(LegacyRecord $record, string $label, LegacyImportLog $log): array;

    /** The stored totals against the derived document (reported when different). */
    abstract protected function compare(LegacyRecord $record, Model $document, string $label, LegacyImportLog $log): void;

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $this->sales = DB::table('sales')->whereNotNull('sales_number')->pluck('id', 'sales_number')->all();
        $batch = 0;

        DB::beginTransaction();
        try {
            foreach ($export->records() as $record) {
                $this->importRecord($record, $log);
                if (++$batch % 300 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }

            $this->rows->syncIdSequence($this->table());
            $this->continueNumbers();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    private function importRecord(LegacyRecord $record, LegacyImportLog $log): void
    {
        $label = $this->idLabel().' '.$record->id();
        $customerId = $this->partners->find($record->int('得意先ID'), $record->exactText('得意先名'));
        if ($customerId === null) {
            $log->skipped("{$label}: 得意先「{$record->text('得意先名')}」が見つかりません");

            return;
        }

        $attributes = $this->attributes($record, $customerId, $label, $log);
        if (DB::table($this->table())->where('number', $attributes['number'])->where(fn ($query) => $query->whereNull('legacy_id')->orWhere('legacy_id', '!=', $record->id()))->exists()) {
            $log->skipped("{$label}: 番号「{$attributes['number']}」は他の書類にあります");

            return;
        }

        $updatedBy = $this->users->id($record->modifier());
        [$id, $created] = $this->rows->save($this->table(), $record->id(), [
            ...$attributes,
            'send_status' => $log->choice($record->text('送付'), SaleChoices::SEND_STATUSES, $label, '送付') ?? SaleChoices::SEND_STATUSES[0],
            'registration_number' => $record->text('登録番号') ?? SaleChoices::REGISTRATION_NUMBER,
            'customer_id' => $customerId,
            'customer_rounding' => $record->text('端数区分') ?? DB::table('partners')->where('id', $customerId)->value('rounding'),
            'notes' => $record->text('備考'),
            'created_by' => $this->users->id($record->creator()),
            'updated_by' => $updatedBy,
            'created_at' => $record->createdAt(),
            'updated_at' => $record->updatedAt(),
        ]);

        $lines = $this->lines($record, $label, $log);
        $saleIds = array_values(array_unique(array_filter(array_column($lines, 'sale_id'))));
        $this->rows->replaceRows($this->linesTable(), $this->foreignKey(), $id, array_map(fn (array $line): array => [
            ...$line,
            'created_at' => $record->createdAt(),
            'updated_at' => $record->updatedAt(),
        ], $lines));
        if ($saleIds !== []) {
            DB::table('sales')->whereIn('id', $saleIds)->update([$this->foreignKey() => $id]);
        }

        $model = $this->model();
        /** @var DeliveryNote|Invoice $document */
        $document = $model::query()->findOrFail($id);
        $document->assignees()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('担当者')))));
        $document->refreshTotals();
        $this->compare($record, $document, $label, $log);

        foreach (['納品書PDF', '請求書PDF'] as $code) {
            if ($record->has($code) && ($files = $record->files($code)) !== []) {
                $log->warn("{$label}: {$code} は移行されません（".implode('、', $files).'）');
            }
        }

        if (! $created) {
            $log->updated();

            return;
        }

        $this->rows->startHistory($document, $updatedBy, $record->updatedAt());
        $log->created();
    }

    /** The sale of a row by its 売上No (null when 06 has none). */
    protected function sale(?string $salesNumber, string $label, LegacyImportLog $log): ?int
    {
        if ($salesNumber === null) {
            return null;
        }

        // rows of older documents hold 売上No without the leading zeros (「463」 = 0000463)
        $id = $this->sales[$salesNumber] ?? (ctype_digit($salesNumber) ? ($this->sales[str_pad($salesNumber, 7, '0', STR_PAD_LEFT)] ?? null) : null);
        if ($id === null) {
            $log->warn("{$label}: 売上No「{$salesNumber}」の売上が見つかりません");
        }

        return $id;
    }

    protected static function taxRate(?string $rate): string
    {
        return (TaxRate::tryFrom((string) $rate) ?? TaxRate::Standard)->value;
    }

    private function continueNumbers(): void
    {
        $highest = 0;
        foreach (DB::table($this->table())->pluck('number') as $number) {
            if (is_string($number) && preg_match('/^\d+$/', $number) === 1) {
                $highest = max($highest, (int) $number);
            }
        }

        $model = $this->model();
        if ($highest > 0) {
            $this->numbers->ensureNextAtLeast($model::numberSequence(), $highest + 1);
        }
    }
}
