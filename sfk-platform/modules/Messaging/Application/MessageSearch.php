<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Domain\Search\SearchText;
use Modules\Messaging\Domain\MessageText;

/**
 * メッセージ検索 over the conversations the user is a member of — by words (folded like the list search: 全角 / 半角,
 * case, カナ / かな; trigram-indexed on PostgreSQL), sender, period, file name, and optionally one conversation.
 * Only what the user may read (after their floor, not hidden by them, not unsent); 30 results a page, newest first,
 * keyset `before`. Each result links to the message (the chat opens around it).
 */
final class MessageSearch
{
    private const PAGE = 30;

    /** the search's type filters */
    public const KINDS = ['direct', 'group', 'channel', 'images', 'documents', 'links', 'pinned'];

    public function __construct(private readonly Directory $directory) {}

    /**
     * @param  array{q?: string|null, user?: int|null, from?: string|null, to?: string|null, conversation?: int|null, files?: bool, kind?: string|null, before?: int|null}  $filters
     * @return array{results: list<array<string, mixed>>, more: bool}
     */
    public function search(User $me, array $filters): array
    {
        $words = array_values(array_filter(preg_split('/[\s\x{3000}]+/u', trim((string) ($filters['q'] ?? ''))) ?: [], fn (string $word): bool => $word !== ''));
        $files = (bool) ($filters['files'] ?? false);
        $kind = in_array($filters['kind'] ?? null, self::KINDS, true) ? (string) $filters['kind'] : null;
        if ($words === [] && empty($filters['user']) && empty($filters['from']) && empty($filters['to']) && $kind === null) {
            return ['results' => [], 'more' => false];
        }

        $query = DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($me): void {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', '=', $me->id)->whereNull('p.left_at');
            })
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereRaw('messages.id > COALESCE(p.visible_from_message_id, 0)')
            ->whereRaw('messages.id > COALESCE(p.cleared_message_id, 0)')
            ->whereNull('messages.unsent_at')
            ->where('messages.type', '!=', 'system')
            ->whereNotExists(fn (Builder $hidden) => $hidden->selectRaw('1')->from('message_hides')
                ->whereColumn('message_hides.message_id', 'messages.id')->where('message_hides.user_id', $me->id));

        if (! empty($filters['conversation'])) {
            $query->where('messages.conversation_id', (int) $filters['conversation']);
        }
        if (! empty($filters['user'])) {
            $query->where('messages.user_id', (int) $filters['user']);
        }
        if (! empty($filters['from'])) {
            $query->where('messages.created_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('messages.created_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay());
        }
        // 個人 / グループ / チャンネル / 画像 / ドキュメント / リンク / ピン留め
        match ($kind) {
            'direct', 'group', 'channel' => $query->where('conversations.type', $kind),
            'images' => $query->where('messages.type', 'image'),
            'documents' => $query->where('messages.type', 'file'),
            'links' => $query->where(fn (Builder $either) => $either->where('messages.body', 'like', '%http://%')->orWhere('messages.body', 'like', '%https://%')),
            'pinned' => $query->whereExists(fn (Builder $pins) => $pins->selectRaw('1')->from('message_pins')->whereColumn('message_pins.message_id', 'messages.id')),
            default => null,
        };
        if (! empty($filters['before'])) {
            $query->where('messages.id', '<', (int) $filters['before']);
        }

        foreach ($words as $word) {
            if ($files) {
                $query->whereExists(fn (Builder $attachments) => SearchQuery::where(
                    $attachments->selectRaw('1')->from('message_attachments')->whereColumn('message_attachments.message_id', 'messages.id'),
                    'message_attachments.original_name',
                    $word,
                ));
            } else {
                $query->where(function (Builder $either) use ($word): void {
                    SearchQuery::where($either, 'messages.body', $word);
                    $either->orWhereExists(fn (Builder $attachments) => SearchQuery::where(
                        $attachments->selectRaw('1')->from('message_attachments')->whereColumn('message_attachments.message_id', 'messages.id'),
                        'message_attachments.original_name',
                        $word,
                    ));
                });
            }
        }
        if ($files && $words === []) {
            $query->whereIn('messages.type', ['file', 'image', 'voice']);
        }

        $rows = $query->orderByDesc('messages.id')
            ->limit(self::PAGE + 1)
            ->get(['messages.id', 'messages.conversation_id', 'messages.user_id', 'messages.type', 'messages.body', 'messages.meta', 'messages.created_at',
                'conversations.type as conversation_type', 'conversations.name as conversation_name', 'conversations.direct_key']);

        $results = $rows->take(self::PAGE)->map(function (object $row) use ($me, $words): array {
            $meta = is_string($row->meta) ? (array) json_decode($row->meta, true) : [];
            $peer = 0;
            if ($row->conversation_type === 'direct' && is_string($row->direct_key)) {
                [$a, $b] = array_map('intval', explode(':', $row->direct_key) + [1 => 0]);
                $peer = $a === $me->id ? $b : $a;
            }

            return [
                'id' => (int) $row->id,
                'conversation' => (int) $row->conversation_id,
                'conversation_name' => $row->conversation_type !== 'direct' ? (string) $row->conversation_name : $this->directory->name($peer),
                'conversation_type' => (string) $row->conversation_type,
                'file' => isset($meta['file']) ? (string) $meta['file'] : null,
                'user' => $row->user_id !== null ? (int) $row->user_id : null,
                'type' => (string) $row->type,
                'text' => self::excerpt((string) ($row->body ?? ''), $words) ?: (string) ($meta['file'] ?? ''),
                'at' => Carbon::parse((string) $row->created_at)->toIso8601String(),
            ];
        })->values()->all();

        return ['results' => $results, 'more' => $rows->count() > self::PAGE];
    }

    /**
     * A part of the text around the first word found.
     *
     * @param  list<string>  $words
     */
    private static function excerpt(string $text, array $words): string
    {
        $line = MessageText::preview($text, 10000);
        foreach ($words as $word) {
            foreach (SearchText::shortVariants($word) as $variant) {
                $at = mb_stripos($line, $variant);
                if ($at !== false) {
                    $start = max(0, $at - 20);

                    return ($start > 0 ? '…' : '').MessageText::preview(mb_substr($line, $start), 90);
                }
            }
        }

        return MessageText::preview($line, 90);
    }
}
