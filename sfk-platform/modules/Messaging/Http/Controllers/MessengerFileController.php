<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Messaging\Application\Announcements;
use Modules\Messaging\Application\AttachmentStore;
use Modules\Messaging\Application\MessagingAccess;
use Modules\Messaging\Application\Stickers;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The files of the messenger, each read only after its permission was checked: message attachments (a member of the
 * conversation who may see the message), group images (a member), announcement files (a recipient or an administrator)
 * and company stickers (any signed-in user). Nothing is served from a public or guessable address.
 */
final class MessengerFileController
{
    public function __construct(
        private readonly MessagingAccess $access,
        private readonly AttachmentStore $store,
    ) {}

    public function attachment(Request $request, int $attachment): Response
    {
        $file = $this->access->attachment(self::user($request), $attachment);

        return $this->store->response($file->path, $file->original_name, $file->mime, $request->boolean('download'));
    }

    public function avatar(Request $request, int $conversation): Response
    {
        $participant = $this->access->member(self::user($request), $conversation);
        $path = $participant->conversation->avatar_path;
        if ($path === null) {
            throw new NotFoundHttpException;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg',
        };

        return $this->store->response($path, 'group.'.$extension, $mime, false);
    }

    public function announcementFile(Request $request, int $file, Announcements $announcements): Response
    {
        $attachment = $announcements->file(self::user($request), $file);

        return $this->store->response($attachment->path, $attachment->original_name, $attachment->mime, $request->boolean('download'));
    }

    public function sticker(Request $request, int $sticker, Stickers $stickers): Response
    {
        self::user($request);
        $found = $stickers->company()[$sticker] ?? null;
        if ($found === null) {
            throw new NotFoundHttpException;
        }

        return $this->store->response($found['path'], $found['name'], $found['mime'], false);
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpException(401);
        }

        return $user;
    }
}
