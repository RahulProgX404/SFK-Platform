<?php

/*
 * 通知 (Core Notifier): the bell of every dashboard and the e-mail copy — who receives a 通知 (not the actor, not users
 * who left, not who switched the kind off, only users who may open the record, once per Notice::once() key),
 * プロフィール → 通知設定, the daily checks and the pruning (SYSTEM_BRAIN 11_PERMISSIONS §3, 16_REBUILD_SPEC
 * "Notifications").
 */

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\NotificationCheck;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Application\Notifications\NotificationPreferences;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Application\Notifications\SystemNotificationMail;
use Modules\Core\Domain\People\EmploymentStatus;
use Modules\Core\Filament\Profile\ProfilePage;
use Modules\Core\Models\NotificationSetting;
use Modules\Core\Models\Position;

/** Records of the test: Position rows, readable by the users listed in $visibleTo, linked to /records/<id>. */
final class TestRecordDirectory implements RecordDirectory
{
    /** @var list<int> */
    public array $visibleTo = [];

    public function url(Model $record): ?string
    {
        return 'http://localhost/records/'.$record->getKey();
    }

    public function appName(Model $record): ?string
    {
        return '99. テストアプリ';
    }

    public function visibleTo(User $user, Model $record): bool
    {
        return in_array($user->id, $this->visibleTo, true);
    }
}

final class TestNotificationCheck implements NotificationCheck
{
    public static int $runs = 0;

    public function run(CarbonImmutable $today): void
    {
        self::$runs++;
        app(Notifier::class)->send(Notice::make('test.daily')->title('毎日')->once('test.daily:'.$today->toDateString()), User::query()->get());
    }
}

beforeEach(function () {
    $kinds = app(NotificationKinds::class);
    $kinds->register(new NotificationKind(key: 'test.kind', label: 'テスト通知', appCode: 'partner-master'));
    $kinds->register(new NotificationKind(key: 'test.admin', label: '管理者向け', forAdministrators: true));
    $kinds->register(new NotificationKind(key: 'test.daily', label: '毎日の通知', check: TestNotificationCheck::class));
    $this->directory = new TestRecordDirectory;
    app()->instance(RecordDirectory::class, $this->directory);
    app()->forgetInstance(Notifier::class);
});

it('writes the 通知 for the bell with its kind and link, never to the actor or users who left', function () {
    $actor = User::factory()->create(['name' => '書いた人']);
    $colleague = User::factory()->create();
    $left = User::factory()->create(['employment_status' => EmploymentStatus::Resigned]);
    $onLeave = User::factory()->create(['employment_status' => EmploymentStatus::OnLeave]);

    $sent = app(Notifier::class)->send(
        Notice::make('test.kind')->title('お知らせ')->body('本文')->url('http://localhost/x'),
        [$actor, $colleague, $colleague, $left, $onLeave, null],
        $actor,
    );

    expect(array_map(fn (User $user): int => $user->id, $sent))->toBe([$colleague->id, $onLeave->id])
        ->and($actor->notifications()->count())->toBe(0)
        ->and($left->notifications()->count())->toBe(0);

    $notice = $colleague->notifications()->sole();
    expect($notice->type)->toBe('test.kind')
        ->and($notice->data['format'])->toBe('filament')
        ->and($notice->data['kind'])->toBe('test.kind')
        ->and($notice->data['title'])->toBe('お知らせ')
        ->and($notice->data['body'])->toBe('本文')
        ->and($notice->data['actions'][0]['label'])->toBe('レコードを開く')
        ->and($notice->data['actions'][0]['url'])->toBe('http://localhost/x');
});

it('sends a 通知 about a record only to users who may open it, titled and linked by its app', function () {
    $reader = User::factory()->create();
    $outsider = User::factory()->create();
    $record = Position::query()->create(['name' => '主任']);
    $this->directory->visibleTo = [$reader->id];

    app(Notifier::class)->send(Notice::make('test.kind')->about($record, '主任'), [$reader, $outsider]);

    expect($outsider->notifications()->count())->toBe(0)
        ->and($reader->notifications()->sole()->data['title'])->toBe('99. テストアプリ: 主任')
        ->and($reader->notifications()->sole()->data['actions'][0]['url'])->toBe('http://localhost/records/'.$record->id);
});

it('sends a once-key only once per user, and administration kinds to administrators only', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $notifier = app(Notifier::class);

    $notifier->send(Notice::make('test.kind')->title('一度だけ')->once('key-1'), [$user]);
    $user->notifications()->update(['read_at' => now()]);
    $notifier->send(Notice::make('test.kind')->title('一度だけ')->once('key-1'), [$user]);
    $notifier->send(Notice::make('test.kind')->title('別のキー')->once('key-2'), [$user]);
    $notifier->send(Notice::make('test.admin')->title('管理'), [$user, $admin]);

    expect($user->notifications()->pluck('data')->pluck('title')->sort()->values()->all())->toBe(['一度だけ', '別のキー'])
        ->and($admin->notifications()->sole()->data['title'])->toBe('管理');
});

it('refuses a kind nobody registered', function () {
    app(Notifier::class)->send(Notice::make('test.unknown')->title('x'), [User::factory()->create()]);
})->throws(InvalidArgumentException::class);

it('follows 通知設定: a kind switched off is not received, メール sends a copy after the commit', function () {
    Notification::fake();
    $off = User::factory()->create();
    $mail = User::factory()->create(['email' => 'mail@example.com', 'contact_email' => 'contact@example.com']);
    NotificationSetting::query()->create(['user_id' => $off->id, 'kind' => 'test.kind', 'receive' => false, 'mail' => false]);
    NotificationSetting::query()->create(['user_id' => $mail->id, 'kind' => 'test.kind', 'receive' => true, 'mail' => true]);

    app(Notifier::class)->send(Notice::make('test.kind')->title('件名')->body('本文')->url('http://localhost/y'), [$off, $mail]);

    Notification::assertNothingSentTo($off);
    Notification::assertSentTo($mail, SystemNotificationMail::class, function (SystemNotificationMail $notification, array $channels) use ($mail): bool {
        $message = $notification->toMail($mail);

        return $channels === ['mail'] && $notification->afterCommit === true && $message->subject === '【'.config('app.name').'】件名'
            && $mail->routeNotificationForMail() === 'contact@example.com';
    });
});

it('keeps 通知設定 on the profile page: kinds of the user, 受け取る and メールでも受け取る', function () {
    $user = grantDashboards(User::factory()->create(), 'erp');
    Filament::setCurrentPanel('erp');
    $this->actingAs($user);
    $receive = array_values(array_diff(app(NotificationPreferences::class)->receiving($user), ['test.kind']));

    Livewire::test(ProfilePage::class)
        ->assertSee(['通知設定', '受け取る', 'メールでも受け取る', 'コメント', 'テスト通知', '毎日の通知'])
        ->assertDontSee('管理者向け')
        ->assertSchemaStateSet(['notifications_mail' => []])
        ->fillForm(['notifications_receive' => $receive, 'notifications_mail' => ['test.daily']])
        ->call('save')
        ->assertHasNoFormErrors();

    // テスト通知 off (and so no e-mail), 毎日の通知 with e-mail, the others as by default (no row)
    expect(NotificationSetting::query()->where('user_id', $user->id)->orderBy('kind')->get(['kind', 'receive', 'mail'])->toArray())->toBe([
        ['kind' => 'test.daily', 'receive' => true, 'mail' => true],
        ['kind' => 'test.kind', 'receive' => false, 'mail' => false],
    ]);
    Livewire::test(ProfilePage::class)->assertSchemaStateSet(['notifications_receive' => $receive, 'notifications_mail' => ['test.daily']]);
});

it('runs the daily checks every morning and prunes old 通知 at night', function () {
    TestNotificationCheck::$runs = 0;
    $user = User::factory()->create();

    $this->artisan('core:notification-checks', ['--date' => '2026-10-28'])->assertSuccessful();
    $this->artisan('core:notification-checks', ['--date' => '2026-10-28'])->assertSuccessful();
    expect(TestNotificationCheck::$runs)->toBe(2)->and($user->notifications()->count())->toBe(1);

    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => $event->command.' '.$event->expression)->implode("\n");
    expect($commands)->toContain('core:notification-checks 30 7 * * *')->toContain('core:notifications-prune 0 3 * * *');

    $user->notifications()->update(['read_at' => now()->subDays(181)]);
    app(Notifier::class)->send(Notice::make('test.kind')->title('新しい'), [$user]);
    DatabaseNotification::query()->forceCreate(['id' => (string) Str::uuid(), 'type' => 'test.kind', 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
        'data' => ['format' => 'filament', 'title' => '古い未読'], 'created_at' => now()->subDays(366), 'updated_at' => now()->subDays(366)]);

    $this->artisan('core:notifications-prune')->assertSuccessful();
    expect($user->notifications()->get()->pluck('data.title')->all())->toBe(['新しい']);
});

it('sends every 通知 through the Notifier', function () {
    $direct = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('modules'), FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        if (str_ends_with($path, '.php') && ! str_contains($path, '/Tests/') && str_contains((string) file_get_contents($path), 'sendToDatabase(')) {
            $direct[] = $path;
        }
    }

    expect($direct)->toBe([]);
});
