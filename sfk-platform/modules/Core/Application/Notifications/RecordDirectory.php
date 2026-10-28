<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What the Notifier needs to know about the record a 通知 is about, answered by the app's screens (Core binds the
 * implementation, Modules\Core\Filament\Support\AppRecordDirectory).
 */
interface RecordDirectory
{
    /** The record page, or null when no app shows the record. */
    public function url(Model $record): ?string;

    /** The name of the app showing the record (e.g. 「05. 納品(仕入)入力」), or null when there is none. */
    public function appName(Model $record): ?string;

    /** Whether the user may open the record: app permissions, the app's own narrowing and レコードのアクセス権. */
    public function visibleTo(User $user, Model $record): bool;
}
