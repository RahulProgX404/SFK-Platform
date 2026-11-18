<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

enum AttachmentKind: string
{
    case Image = 'image';
    case File = 'file';
    case Voice = 'voice';
}
