<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Documents;

/** What the browser does with the put-out file (the legacy system showed it in a browser tab, SYSTEM_BRAIN 08). */
enum OutputDelivery: string
{
    /** ブラウザの新しいタブで開く (a ZIP is downloaded anyway) */
    case Open = 'open';
    /** ダウンロードする */
    case Download = 'download';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'ブラウザの新しいタブで開く',
            self::Download => 'ダウンロードする',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return [self::Open->value => self::Open->label(), self::Download->value => self::Download->label()];
    }
}
