<?php

declare(strict_types=1);

namespace Modules\Core\Application\Files;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The disk of the files users upload (契約書, 作業指示書, 見積書 / 納品書 / 請求書の PDF, 帳票テンプレートの背景): config
 * filesystems.uploads (UPLOADS_DISK). The private local disk in development and on a server of our own; a private object
 * storage bucket where the servers keep no files between deploys (Laravel Cloud). Either way the files are private:
 * the browser opens them through temporary URLs.
 */
final class Uploads
{
    /** @var array<string, string|null> per disk configuration */
    private static array $origins = [];

    public static function disk(): string
    {
        return (string) config('filesystems.uploads', 'local');
    }

    public static function storage(): Filesystem
    {
        return Storage::disk(self::disk());
    }

    /** Whether the files are on this server's own filesystem (else in object storage). */
    public static function isLocal(): bool
    {
        return config('filesystems.disks.'.self::disk().'.driver') === 'local';
    }

    /**
     * A file on this server with the upload's contents, for tools that read files by path (the PDF reader, qpdf): the
     * upload itself on a local disk, else a copy under storage/framework/uploads. An upload path never gets other
     * contents (a new upload gets a new name), so a copy stays valid until delete(). Null when the upload is missing.
     */
    public static function localFile(string $path): ?string
    {
        $disk = self::storage();
        if (self::isLocal()) {
            return $disk->exists($path) ? $disk->path($path) : null;
        }

        $copy = self::copyPath($path);
        if (is_file($copy)) {
            return $copy;
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        if (! is_dir(dirname($copy))) {
            @mkdir(dirname($copy), 0775, true);
        }
        // written under another name first: another request never reads a half-written copy
        $partial = $copy.'.'.bin2hex(random_bytes(4));
        $target = fopen($partial, 'wb');
        if ($target === false) {
            fclose($stream);

            return null;
        }
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return rename($partial, $copy) ? $copy : null;
    }

    /** Stores the local file of an upload (localFile(), changed in place) as the upload; nothing to do on a local disk. */
    public static function storeLocalFile(string $path): void
    {
        if (self::isLocal() || ! is_file($copy = self::copyPath($path))) {
            return;
        }

        $stream = fopen($copy, 'rb');
        if ($stream === false) {
            return;
        }
        self::storage()->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /** Deletes an upload, with its local copy. */
    public static function delete(string $path): void
    {
        self::storage()->delete($path);
        if (! self::isLocal() && is_file($copy = self::copyPath($path))) {
            @unlink($copy);
        }
    }

    /**
     * The origin (https://host) the browser loads uploads from when the disk hands out URLs of another site (object
     * storage), for the Content-Security-Policy; null when this system serves them itself.
     */
    public static function origin(): ?string
    {
        $key = self::disk().'|'.json_encode(config('filesystems.disks.'.self::disk()));
        if (array_key_exists($key, self::$origins)) {
            return self::$origins[$key];
        }

        $origin = null;
        if (! self::isLocal()) {
            try {
                // signed locally, no request to the storage
                $url = self::storage()->temporaryUrl('origin', now()->addMinute());
                $parts = parse_url($url);
                if (isset($parts['scheme'], $parts['host'])) {
                    $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                }
            } catch (Throwable) {
                // a disk without temporary URLs: Filament falls back to its public URLs, served from where the disk says
            }
        }

        return self::$origins[$key] = $origin;
    }

    private static function copyPath(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return storage_path('framework/uploads/'.sha1(self::disk().'/'.$path).($extension !== '' ? '.'.$extension : ''));
    }
}
