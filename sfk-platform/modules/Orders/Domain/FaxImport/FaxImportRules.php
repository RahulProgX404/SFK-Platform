<?php

declare(strict_types=1);

namespace Modules\Orders\Domain\FaxImport;

/**
 * Settings of the 受注書 import (config orders.fax_import): whose purchase orders it reads and how their marks turn
 * into order fields.
 */
final readonly class FaxImportRules
{
    /**
     * @param  array<string, string>  $stamps  surname in 検印欄 => 製袋予定規格
     */
    public function __construct(
        public string $issuer,
        public array $stamps,
        public string $priorityStamp,
        public string $prioritySchedule,
        public string $manufacturer,
        public string $category1,
        public string $category2,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromConfig(array $config): self
    {
        /** @var array<string, string> $stamps */
        $stamps = array_map('strval', (array) ($config['stamps'] ?? []));

        return new self(
            (string) ($config['issuer'] ?? ''),
            $stamps,
            (string) ($config['priority_stamp'] ?? ''),
            (string) ($config['priority_schedule'] ?? ''),
            (string) ($config['manufacturer'] ?? ''),
            (string) ($config['category1'] ?? ''),
            (string) ($config['category2'] ?? ''),
        );
    }

    /** @return list<string> */
    public function stampNames(): array
    {
        return array_map('strval', array_keys($this->stamps));
    }
}
