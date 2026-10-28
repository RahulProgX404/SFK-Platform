<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Filament\Support\Icons\Heroicon;

/**
 * One kind of 通知 (e.g. コメント, 仕入原価超過): what the user switches on / off in プロフィール → 通知設定 and how it is drawn
 * in the bell. Registered by the module that sends it (NotificationKinds::register()). A kind with a `check` is also
 * looked for once a day (NotificationCheck, `core:notification-checks`).
 */
final readonly class NotificationKind
{
    /**
     * @param  string  $key  stable id stored with every 通知 (`data.kind`) and in the users' settings, e.g. 'purchasing.purchase.overrun'
     * @param  string  $label  name in 通知設定, e.g. 「仕入原価超過」
     * @param  string|null  $appCode  the app it belongs to (its name is shown next to the label), null for every app
     * @param  bool  $forAdministrators  only administrators receive it (listed in 通知設定 for them only)
     * @param  class-string<NotificationCheck>|null  $check  the daily check that sends it
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $appCode = null,
        public bool $forAdministrators = false,
        public Heroicon $icon = Heroicon::OutlinedBell,
        public string $color = 'primary',
        public ?string $check = null,
    ) {}
}
