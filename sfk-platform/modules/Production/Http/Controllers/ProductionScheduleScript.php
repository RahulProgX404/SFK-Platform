<?php

declare(strict_types=1);

namespace Modules\Production\Http\Controllers;

use Illuminate\Http\Response;

/**
 * The script of the 製袋予定表 (Resources/js/production-schedule.js): the URL carries the file's version, so browsers keep
 * it for a year.
 */
final class ProductionScheduleScript
{
    public const PATH = __DIR__.'/../../Resources/js/production-schedule.js';

    public static function url(): string
    {
        return route('production.schedule-script', ['v' => substr((string) md5_file(self::PATH), 0, 12)]);
    }

    public function __invoke(): Response
    {
        return response((string) file_get_contents(self::PATH), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
