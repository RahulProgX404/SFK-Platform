<?php

declare(strict_types=1);

namespace Modules\Core\Application\Documents;

use Modules\Core\Domain\Documents\DocumentValue;

/**
 * What a 帳票 prints of one record: the values of its fields and the rows of its tables (DocumentSource).
 */
final readonly class RecordData
{
    /**
     * @param  array<string, DocumentValue>  $fields
     * @param  array<string, list<array<string, DocumentValue>>>  $tables
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $fields,
        public array $tables,
    ) {}

    public function value(string $field): DocumentValue
    {
        return $this->fields[$field] ?? DocumentValue::empty();
    }

    /** @return list<array<string, DocumentValue>> */
    public function rows(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    /**
     * The same record with more values (e.g. 出力日 / ページ番号 while drawing).
     *
     * @param  array<string, DocumentValue>  $fields
     */
    public function with(array $fields): self
    {
        return new self($this->key, $this->title, [...$this->fields, ...$fields], $this->tables);
    }
}
