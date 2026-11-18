<?php

declare(strict_types=1);

namespace Modules\Messaging\Http;

/**
 * The messenger's own stylesheet and script (Resources/assets), served by MessengerAssetController with a version of
 * their content — loaded only by the messenger page.
 */
final class MessengerAssets
{
    /** @var array<string, string> */
    private static array $memo = [];

    public static function css(): string
    {
        return self::read('messenger.css');
    }

    public static function js(): string
    {
        return self::read('messenger.js');
    }

    public static function version(): string
    {
        return self::$memo['version'] ??= substr(md5(self::css()."\0".self::js()), 0, 12);
    }

    /** <link> and <script> for the messenger page's <head> (the script registers its Alpine component before Alpine starts). */
    public static function headTags(): string
    {
        $version = self::version();

        return '<link rel="stylesheet" href="'.e(route('messaging.assets.css', ['v' => $version])).'">'
            .'<script src="'.e(route('messaging.assets.js', ['v' => $version])).'"></script>';
    }

    private static function read(string $file): string
    {
        return self::$memo[$file] ??= (string) file_get_contents(dirname(__DIR__).'/Resources/assets/'.$file);
    }
}
