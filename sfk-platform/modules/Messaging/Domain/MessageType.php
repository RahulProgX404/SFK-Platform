<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case File = 'file';
    case Voice = 'voice';
    case Sticker = 'sticker';

    /** a line of the conversation itself (参加 / 退出 / 名前の変更), sent by nobody */
    case System = 'system';

    /** The type of a message carrying these attachments (and text): the first attachment's kind, else text. */
    public static function forAttachments(?AttachmentKind $first): self
    {
        return match ($first) {
            AttachmentKind::Image => self::Image,
            AttachmentKind::File => self::File,
            AttachmentKind::Voice => self::Voice,
            null => self::Text,
        };
    }

    /** Whether the sender may change the text afterwards. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Text, self::Image, self::File], true);
    }
}
