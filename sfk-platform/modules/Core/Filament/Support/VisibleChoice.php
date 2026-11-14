<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\AppResource;

/**
 * Validation of a reference to another app's record chosen on a screen that is not a Filament select (quick entries):
 * it must be one the user may choose there — AppResource::visibleOr(), the same set the choices offer — not any record
 * that exists (security review 2026-11-14: a tampered request could save a reference to a record the user may not see,
 * and read it back through the copies of the lookup).
 */
final readonly class VisibleChoice implements ValidationRule
{
    /**
     * @param  class-string<AppResource>  $resource  the app of the referenced records
     * @param  class-string<Model>  $model
     * @param  (Closure(): (Builder<covariant Model>|\Illuminate\Database\Query\Builder|array<int|string>|int|string|null))|null  $keep  AppResource::visibleOr()
     */
    public function __construct(
        private string $resource,
        private string $model,
        private ?Closure $keep = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $keep = $this->keep !== null ? ($this->keep)() : null;
        if (! is_numeric($value) || ! $this->resource::visibleOr(($this->model)::query(), $keep)->whereKey((int) $value)->exists()) {
            $fail('選択できない値です。');
        }
    }
}
