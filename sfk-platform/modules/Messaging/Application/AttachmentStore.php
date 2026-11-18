<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Files\Uploads;
use Modules\Messaging\Domain\AttachmentKind;
use Modules\Messaging\Models\MessageAttachment;
use Modules\Messaging\Models\Participant;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Files of messages (docs/messaging.md "Files"): uploaded before sending (each with its own progress and error), then
 * attached by the message. Checked strictly — only the listed business types, the content must be what the extension
 * says (finfo, never the browser's claim), a size limit — and stored under a random name on the private uploads disk
 * (object storage on Laravel Cloud). Files are read only through the download route after MessagingAccess said yes;
 * there is no public or guessable address. They are served with nosniff and a sandbox CSP, and only images, voice
 * and PDF open in the browser — everything else downloads.
 */
final class AttachmentStore
{
    /** What the content of each extension may be (finfo). */
    private const MIMES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'txt' => ['text/plain'],
        'webm' => ['audio/webm', 'video/webm'],
        'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'audio/m4a'],
        'mp4' => ['audio/mp4', 'video/mp4'],
    ];

    /** What the browser receives as the type of each extension (never the stored claim of the uploader). */
    private const SERVED = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv; charset=UTF-8', 'txt' => 'text/plain; charset=UTF-8',
        'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4',
    ];

    public function upload(User $me, Participant $participant, UploadedFile $file, bool $voice = false, ?int $durationMs = null): MessageAttachment
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'ファイルをアップロードできませんでした。']);
        }

        $types = (array) config('messaging.attachments.types', []);
        $extension = strtolower($file->getClientOriginalExtension());
        if ($voice && $extension === '') {
            $extension = 'webm';
        }
        $kind = AttachmentKind::tryFrom((string) ($types[$extension] ?? ''));
        if ($kind === null || ($voice && $kind !== AttachmentKind::Voice) || (! $voice && $kind === AttachmentKind::Voice)) {
            throw ValidationException::withMessages(['file' => 'このファイル形式は送信できません。（PDF / Word / Excel / CSV / テキスト / 画像）']);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (int) config('messaging.attachments.max_kb', 20480) * 1024) {
            throw ValidationException::withMessages(['file' => 'ファイルサイズは'.intdiv((int) config('messaging.attachments.max_kb', 20480), 1024).'MBまでです。']);
        }

        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        if (! in_array($detected, self::MIMES[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => 'ファイルの内容が拡張子と一致しません。']);
        }

        $width = $height = null;
        if ($kind === AttachmentKind::Image) {
            $info = @getimagesize((string) $file->getRealPath());
            if ($info === false) {
                throw ValidationException::withMessages(['file' => '画像を読み込めませんでした。']);
            }
            [$width, $height] = [(int) $info[0], (int) $info[1]];
        }
        if ($kind === AttachmentKind::Voice) {
            $max = (int) config('messaging.attachments.voice_max_seconds', 300) * 1000;
            $durationMs = $durationMs !== null ? max(0, min($durationMs, $max)) : null;
        } else {
            $durationMs = null;
        }

        $path = 'messaging/'.$participant->conversation_id.'/'.Str::lower((string) Str::ulid()).'.'.$extension;
        $stream = fopen((string) $file->getRealPath(), 'rb');
        Uploads::storage()->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return MessageAttachment::query()->create([
            'conversation_id' => $participant->conversation_id,
            'message_id' => null,
            'user_id' => $me->id,
            'kind' => $kind,
            'path' => $path,
            'original_name' => self::safeName($file->getClientOriginalName(), $extension, $voice),
            'mime' => self::SERVED[$extension] ?? 'application/octet-stream',
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'duration_ms' => $durationMs,
        ]);
    }

    /** Removes an upload that was not sent (the × of the preview). */
    public function discard(User $me, int $attachmentId): void
    {
        $attachment = MessageAttachment::query()->whereKey($attachmentId)->where('user_id', $me->id)->whereNull('message_id')->first();
        if ($attachment instanceof MessageAttachment) {
            $attachment->delete();
            Uploads::delete($attachment->path);
        }
    }

    /** The file for the browser (already authorized): inline for images, voice and PDF, else a download. */
    public function response(string $path, string $name, string $mime, bool $download): Response
    {
        $storage = Uploads::storage();
        if (! $storage->exists($path)) {
            throw new NotFoundHttpException;
        }

        $inline = ! $download && (str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/') || $mime === 'application/pdf');
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.self::asciiName($name).'"; filename*=UTF-8\'\''.rawurlencode($name);

        if (! Uploads::isLocal()) {
            try {
                // object storage: a short-lived signed address, the browser loads it from the bucket
                return redirect()->away($storage->temporaryUrl($path, now()->addMinutes(5), [
                    'ResponseContentType' => $mime,
                    'ResponseContentDisposition' => $disposition,
                ]));
            } catch (\Throwable) {
                // a disk without signed addresses: streamed below
            }
        }

        return new StreamedResponse(function () use ($storage, $path): void {
            $stream = $storage->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $storage->size($path),
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private static function safeName(string $name, string $extension, bool $voice): string
    {
        if ($voice) {
            return 'ボイスメッセージ_'.now()->format('Ymd_His').'.'.$extension;
        }

        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name);
        $name = trim($name, " .\t") ?: 'ファイル.'.$extension;
        if (mb_strlen($name) > 180) {
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 170).'.'.$extension;
        }

        return $name;
    }

    private static function asciiName(string $name): string
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);

        return trim($ascii, '_') !== '' ? $ascii : 'file';
    }
}
