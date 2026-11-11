<?php

declare(strict_types=1);

namespace Modules\Analytics\Application;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Modules\Core\Application\Cache\DataCache;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\RecordComment;
use Modules\Core\Models\RecordVersion;

/**
 * 最近のアクティビティ of the dashboard (row 4, every user): only what concerns the user —
 * - what others did that concerns them: their 通知 (the Notifier sends a 通知 only to those concerned who may see the
 *   record: mentions, the steps of a workflow, records assigned to them …);
 * - what they did themselves: records they added / changed (変更履歴) and their コメント, as far as they still may see
 *   the record.
 * Newest first.
 */
final class ActivityFeed
{
    public function __construct(private readonly RecordDirectory $records) {}

    /**
     * @return list<array{kind: string, title: string, body: string|null, at: CarbonInterface, url: string|null, by: string|null}>
     *                                                                                                                             kind: notice | created | updated | comment
     */
    public function for(User $user, int $limit = 8): array
    {
        // from the cache (DataCache — notices, 変更履歴 and コメント are records, so any change adds them up afresh)
        $items = DataCache::remember('activity:'.$user->id.':'.$limit, fn (): array => array_map(
            fn (array $item): array => [...$item, 'at' => $item['at']->toIso8601String()],
            $this->read($user, $limit),
        ));

        return array_map(fn (array $item): array => [...$item, 'at' => CarbonImmutable::parse($item['at'])->setTimezone(config('app.timezone'))], $items);
    }

    /** @return list<array{kind: string, title: string, body: string|null, at: CarbonInterface, url: string|null, by: string|null}> */
    private function read(User $user, int $limit): array
    {
        /** @var list<array{kind: string, title: string, body: string|null, at: CarbonInterface, url: string|null, by: string|null}> $items */
        $items = [];

        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)
            ->latest()->limit($limit)->get();
        foreach ($notifications as $notification) {
            $data = (array) $notification->getAttribute('data');
            $at = $notification->getAttribute('created_at');
            if (! $at instanceof CarbonInterface) {
                continue;
            }
            $items[] = [
                'kind' => 'notice',
                'title' => (string) ($data['title'] ?? '通知'),
                'body' => isset($data['body']) ? strip_tags((string) $data['body']) : null,
                'at' => $at,
                'url' => is_string($url = Arr::get($data, 'actions.0.url')) ? $url : null,
                'by' => null,
            ];
        }

        $versions = RecordVersion::query()->where('user_id', $user->id)->latest('id')->limit($limit * 2)->get();
        foreach ($versions as $version) {
            $record = $this->record($version->record_type, $version->record_id, $user);
            if ($record !== null && $version->created_at !== null) {
                [$app, $title, $url] = $record;
                $items[] = [
                    'kind' => $version->event === 'created' ? 'created' : 'updated',
                    'title' => $app.'：'.match ($version->event) {
                        'created' => 'レコードを追加しました',
                        'restored' => 'レコードを元に戻しました',
                        default => 'レコードを更新しました',
                    },
                    'body' => $title,
                    'at' => $version->created_at,
                    'url' => $url,
                    'by' => $user->name,
                ];
            }
        }

        $comments = RecordComment::query()->where('user_id', $user->id)->latest('id')->limit($limit)->get();
        foreach ($comments as $comment) {
            $record = $this->record($comment->record_type, $comment->record_id, $user);
            if ($record !== null && $comment->created_at !== null) {
                [$app, $title, $url] = $record;
                $items[] = [
                    'kind' => 'comment',
                    'title' => $app.'：コメントしました',
                    'body' => $title.'「'.mb_strimwidth(trim(strip_tags($comment->body)), 0, 60, '…').'」',
                    'at' => $comment->created_at,
                    'url' => $url,
                    'by' => $user->name,
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($items, 0, $limit);
    }

    /** @return array{0: string, 1: string, 2: string|null}|null app name, record title, record page — while the user may see it */
    private function record(string $type, int $id, User $user): ?array
    {
        $class = Relation::getMorphedModel($type) ?? $type;
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }
        $record = $class::query()->find($id);
        if (! $record instanceof Model || ! $this->records->visibleTo($user, $record)) {
            return null;
        }

        $resource = Filament::getCurrentOrDefaultPanel()->getModelResource($record);
        // the app's own name of a record (titleOf(), e.g. 「2026-11-10 有給休暇」), else Filament's record title
        $title = match (true) {
            $resource === null || ! is_subclass_of($resource, AppResource::class) => '#'.$id,
            method_exists($resource, 'titleOf') => (string) $resource::titleOf($record),
            default => strip_tags((string) $resource::getRecordTitle($record)),
        };

        return [(string) ($this->records->appName($record) ?? ''), $title, $this->records->url($record)];
    }
}
