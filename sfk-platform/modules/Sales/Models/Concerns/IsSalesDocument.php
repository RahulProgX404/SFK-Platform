<?php

declare(strict_types=1);

namespace Modules\Sales\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Models\Concerns\RecordsHistory;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Documents\TaxBreakdown;

/**
 * 納品書 / 請求書: number from the document sequence (BR-SA-14, 7 digits, numbers already used are skipped), the
 * customer with its 端数区分 copied when chosen, 担当者, and the rows. The totals come from the rows: subtotal and tax per
 * rate on the document (BR-TX-04) — refreshTotals() after the rows changed. 変更履歴 keeps the columns, 担当者 and the
 * rows.
 *
 * @mixin Model
 *
 * @property string $number
 * @property int|null $customer_id
 * @property string|null $customer_rounding
 * @property int $subtotal
 * @property int $tax_total
 * @property-read Partner|null $customer
 * @property-read Collection<int, User> $assignees
 */
trait IsSalesDocument
{
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    abstract public static function numberSequence(): string;

    /** @return class-string<Model> */
    abstract public static function lineModel(): string;

    public static function bootIsSalesDocument(): void
    {
        static::creating(function (Model $document): void {
            if (blank($document->getAttribute('number'))) {
                $numbers = app(DocumentNumberGenerator::class);
                do {
                    $number = $numbers->next(static::numberSequence());
                } while (static::query()->where('number', $number)->exists());
                $document->setAttribute('number', $number);
            }
        });

        static::saving(function (Model $document): void {
            if ($document->isDirty('customer_id') && ! $document->isDirty('customer_rounding')) {
                $customer = $document->getAttribute('customer_id') === null ? null : Partner::query()->find($document->getAttribute('customer_id'));
                $document->setAttribute('customer_rounding', $customer?->rounding?->value);
                $document->unsetRelation('customer');
            }
        });
    }

    /** 端数区分 of the customer as of the document (blank ⇒ 切捨, BR-TX-01). */
    public function roundingMode(): RoundingMode
    {
        return RoundingMode::fromLabel($this->customer_rounding);
    }

    /** Subtotals and taxes per rate of the saved rows. */
    public function taxBreakdown(): TaxBreakdown
    {
        $lines = $this->relationLoaded('lines') ? $this->getRelation('lines') : $this->lines()->get();

        return TaxBreakdown::of(
            $lines->map(fn (Model $line): array => ['tax_rate' => (string) $line->getAttribute('tax_rate'), 'amount' => (int) $line->getAttribute('amount')]),
            $this->roundingMode(),
        );
    }

    /** Writes the totals of the saved rows (saved without events when something changed). */
    public function refreshTotals(): void
    {
        $this->unsetRelation('lines');
        $this->fillTotals($this->taxBreakdown());
        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    protected function fillTotals(TaxBreakdown $breakdown): void
    {
        $this->setAttribute('subtotal', $breakdown->subtotal());
        $this->setAttribute('tax_total', $breakdown->tax());
    }

    /**
     * 変更履歴: the columns, 担当者 (user ids) and the rows.
     *
     * @return array<string, mixed>
     */
    public function historySnapshot(): array
    {
        $line = static::lineModel();
        $snapshot = $this->columnSnapshot();
        $snapshot['assignees'] = $this->assignees()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $snapshot['lines'] = $this->lines()->orderBy('sort')->get()
            ->map(fn (Model $row): array => array_intersect_key($row->attributesToArray(), array_flip($line::COLUMNS)))
            ->all();
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $line = static::lineModel();
        $copy = $this->columnPreview($snapshot);
        $copy->setRelation('assignees', User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->orderBy('name')->get());
        $copy->setRelation('lines', new Collection(array_map(
            fn (mixed $row): Model => (new $line)->forceFill(array_intersect_key((array) $row, array_flip($line::COLUMNS))),
            (array) ($snapshot['lines'] ?? []),
        )));

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        $line = static::lineModel();

        DB::transaction(function () use ($snapshot, $line): void {
            $this->restoreColumns($snapshot);
            $this->assignees()->sync(User::query()->whereIn('id', (array) ($snapshot['assignees'] ?? []))->pluck('id')->all());
            $this->lines()->delete();
            foreach (array_values((array) ($snapshot['lines'] ?? [])) as $sort => $row) {
                $this->lines()->create([...array_intersect_key((array) $row, array_flip($line::COLUMNS)), 'sort' => $sort]);
            }
            $this->unsetRelation('assignees');
            $this->refreshTotals();
        });
    }

    /** @return BelongsTo<Partner, $this> 得意先名 */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }
}
