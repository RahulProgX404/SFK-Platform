<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Files\Uploads;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageAttachment;

/**
 * Nightly clean-up (docs/messaging.md "Retention"):
 * - files uploaded but never sent (after messaging.attachments.pending_hours);
 * - when the company sets a retention (MESSAGING_RETENTION_DAYS), messages older than it, with their files. Off by
 *   default: messages are kept. A stored file shared by forwarded copies is removed only with its last copy.
 */
final class PruneMessagingCommand extends Command
{
    protected $signature = 'messaging:prune';

    protected $description = 'Remove unsent uploads and, with a retention set, old messages and their files';

    public function handle(): int
    {
        $pending = MessageAttachment::query()
            ->whereNull('message_id')
            ->where('created_at', '<', now()->subHours((int) config('messaging.attachments.pending_hours', 24)))
            ->get();
        $this->removeFiles($pending->all());
        $this->info($pending->count().' unsent upload(s) removed.');

        $days = config('messaging.retention_days');
        if ($days === null || (int) $days <= 0) {
            return self::SUCCESS;
        }

        $before = now()->subDays((int) $days);
        $removed = 0;
        Message::query()->where('created_at', '<', $before)->orderBy('id')->chunkById(500, function ($messages) use (&$removed): void {
            $ids = $messages->pluck('id')->all();
            $files = MessageAttachment::query()->whereIn('message_id', $ids)->get();
            DB::transaction(function () use ($ids): void {
                // the conversations whose newest message goes keep their place in the inbox without a preview
                Conversation::query()->whereIn('last_message_id', $ids)->update(['last_message_id' => null]);
                Message::query()->whereIn('reply_to_id', $ids)->update(['reply_to_id' => null]);
                Message::query()->whereIn('id', $ids)->delete();
            });
            $this->removeFiles($files->all());
            $removed += count($ids);
        });
        $this->info("{$removed} message(s) older than {$days} days removed.");

        return self::SUCCESS;
    }

    /** @param  list<MessageAttachment>  $files */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if ($file->exists) {
                $file->delete();
            }
            if (! MessageAttachment::query()->where('path', $file->path)->exists()) {
                Uploads::delete($file->path);
            }
        }
    }
}
