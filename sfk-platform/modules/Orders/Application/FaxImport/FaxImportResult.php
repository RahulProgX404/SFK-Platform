<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

/**
 * A read purchase order, for フォームに反映する内容を確認: the values for the form (by attribute), the preview rows, the
 * fields not read, the places the AI was unsure of and the whole reading.
 */
final readonly class FaxImportResult
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<array{field: string, label: string, value: string, source: string, warning: string|null}>  $fields
     * @param  list<string>  $errors
     * @param  list<string>  $uncertain
     * @param  array<string, mixed>  $reading
     */
    public function __construct(
        public string $fileName,
        public string $stamp,
        public string $stampSource,
        public array $values,
        public array $fields,
        public array $errors,
        public array $uncertain,
        public array $reading,
    ) {}

    /** @return array<string, mixed> for the page (Livewire state) */
    public function toArray(): array
    {
        return [
            'fileName' => $this->fileName,
            'stamp' => $this->stamp,
            'stampSource' => $this->stampSource,
            'values' => $this->values,
            'fields' => $this->fields,
            'errors' => $this->errors,
            'uncertain' => $this->uncertain,
            'reading' => $this->reading,
        ];
    }
}
