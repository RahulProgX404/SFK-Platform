<?php

declare(strict_types=1);

namespace Modules\Core\Application\Legacy;

use Generator;
use InvalidArgumentException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/**
 * A record export (backup) of one app of the legacy system: {app: {appId, name, …}, exportedAt, totalCount, fields,
 * layout, records: [{field code: {type, value}}]} — e.g. data_json/0009_04. 受注入力.json. The records are read one at
 * a time (halaxa/json-machine, Apache-2.0): the largest exports are close to 200 MB.
 */
final class LegacyExport
{
    /** @var array<string, mixed>|null */
    private ?array $app = null;

    private function __construct(public readonly string $path) {}

    public static function open(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("ファイルが見つかりません: {$path}");
        }

        return new self($path);
    }

    /** The legacy app id (e.g. '9' for 04. 受注入力). */
    public function appId(): string
    {
        return (string) ($this->app()['appId'] ?? '');
    }

    public function appName(): string
    {
        return (string) ($this->app()['name'] ?? '');
    }

    /** The number of records the export says it holds. */
    public function totalCount(): int
    {
        foreach (Items::fromFile($this->path, ['pointer' => '/totalCount']) as $count) {
            return (int) $count;
        }

        return 0;
    }

    /** @return Generator<int, LegacyRecord> the records, in the order of the export */
    public function records(): Generator
    {
        $items = Items::fromFile($this->path, ['pointer' => '/records', 'decoder' => new ExtJsonDecoder(true)]);
        foreach ($items as $record) {
            if (is_array($record)) {
                yield new LegacyRecord($record);
            }
        }
    }

    /** @return array<string, mixed> */
    private function app(): array
    {
        if ($this->app === null) {
            $this->app = [];
            foreach (Items::fromFile($this->path, ['pointer' => '/app', 'decoder' => new ExtJsonDecoder(true)]) as $key => $value) {
                $this->app[(string) $key] = $value;
            }
        }

        return $this->app;
    }
}
