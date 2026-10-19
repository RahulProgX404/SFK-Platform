<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\FaxImport;

/**
 * What a read purchase order puts into the new order: the values by order attribute (partners still by name:
 * `customer_name`, `manufacturer_name`), the preview rows (項目 / フォームに入る値 / 抽出元 / 要確認) and the fields
 * that could not be read.
 */
final readonly class FaxOrderMapping
{
    /**
     * @param  array<string, string>  $values
     * @param  list<array{field: string, label: string, value: string, source: string, warning: string|null}>  $fields
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $values,
        public array $fields,
        public array $errors,
    ) {}
}
