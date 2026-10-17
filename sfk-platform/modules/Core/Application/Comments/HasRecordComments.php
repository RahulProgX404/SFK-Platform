<?php

declare(strict_types=1);

namespace Modules\Core\Application\Comments;

use App\Models\User;

/**
 * A record that takes コメント (legacy record comments). Use Modules\Core\Models\Concerns\RecordsComments, which also
 * deletes the comments with the record, and return the app's comment recipients (legacy app notification on
 * 「コメントの書き込み」, e.g. 作成者 / 更新者).
 */
interface HasRecordComments
{
    /**
     * Who is told about every new comment besides its 宛先 (the writer never is).
     *
     * @return iterable<User|null>
     */
    public function commentRecipients(): iterable;
}
