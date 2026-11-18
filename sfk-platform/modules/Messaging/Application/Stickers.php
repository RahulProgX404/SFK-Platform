<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Files\Uploads;
use Modules\Messaging\Models\Sticker;

/**
 * スタンプ: a built-in set of the company's own business stickers (an emoji with a Japanese phrase, drawn by the
 * messenger — no third-party sticker art) and the company stickers administrators upload (images on the private
 * uploads disk). A sticker message stores only its key: 'b:<name>' built-in, 'c:<id>' company.
 */
final class Stickers
{
    /** @var array<string, array{0: string, 1: string}> name => [emoji, phrase] */
    public const BUILT_IN = [
        'ohayo' => ['☀️', 'おはようございます'],
        'otsukare' => ['🍵', 'お疲れ様です'],
        'yoroshiku' => ['🙇', 'よろしくお願いします'],
        'ryokai' => ['👌', '了解です'],
        'shochi' => ['🫡', '承知しました'],
        'arigato' => ['🙏', 'ありがとうございます'],
        'kakunin' => ['🔍', '確認します'],
        'matte' => ['⏳', '少々お待ちください'],
        'mukau' => ['🏃', '今から向かいます'],
        'sumimasen' => ['💦', '申し訳ありません'],
        'iine' => ['✨', 'いいですね！'],
        'ganbaro' => ['💪', '頑張りましょう'],
        'omedeto' => ['🎉', 'おめでとうございます'],
        'shitsurei' => ['🌙', 'お先に失礼します'],
        'kanryo' => ['✅', '完了しました'],
        'isogi' => ['🚨', '至急お願いします'],
    ];

    /**
     * Every sticker for the picker.
     *
     * @return list<array{key: string, emoji?: string, text?: string, url?: string, name?: string}>
     */
    public function catalog(): array
    {
        $stickers = [];
        foreach (self::BUILT_IN as $name => [$emoji, $text]) {
            $stickers[] = ['key' => 'b:'.$name, 'emoji' => $emoji, 'text' => $text];
        }
        foreach ($this->company() as $sticker) {
            $stickers[] = ['key' => 'c:'.$sticker['id'], 'url' => route('messaging.stickers.show', ['sticker' => $sticker['id']]), 'name' => $sticker['name']];
        }

        return $stickers;
    }

    public function exists(string $key): bool
    {
        if (str_starts_with($key, 'b:')) {
            return array_key_exists(substr($key, 2), self::BUILT_IN);
        }

        return str_starts_with($key, 'c:') && ctype_digit(substr($key, 2)) && array_key_exists((int) substr($key, 2), $this->company());
    }

    public function add(string $name, UploadedFile $file, int $userId): Sticker
    {
        $info = @getimagesize((string) $file->getRealPath());
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF, IMAGETYPE_JPEG], true) || $file->getSize() > 1024 * 1024) {
            throw ValidationException::withMessages(['file' => '1MB以下の画像（PNG / WEBP / GIF / JPG）を選んでください。']);
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 50) {
            throw ValidationException::withMessages(['name' => 'スタンプ名を50文字以内で入力してください。']);
        }

        $path = 'messaging/stickers/'.Str::lower((string) Str::ulid()).'.'.(image_type_to_extension($info[2], false) ?: 'img');
        Uploads::storage()->put($path, (string) file_get_contents((string) $file->getRealPath()));

        $sticker = Sticker::query()->create([
            'name' => $name,
            'path' => $path,
            'mime' => (string) $info['mime'],
            'sort_order' => (int) Sticker::query()->max('sort_order') + 1,
            'created_by' => $userId,
        ]);
        ReferenceCache::bump();

        return $sticker;
    }

    public function remove(int $id): void
    {
        $sticker = Sticker::query()->find($id);
        if ($sticker instanceof Sticker) {
            Uploads::delete($sticker->path);
            $sticker->delete();
            ReferenceCache::bump();
        }
    }

    /** @return array<int, array{id: int, name: string, path: string, mime: string}> */
    public function company(): array
    {
        return ReferenceCache::remember('messaging-stickers', fn (): array => Sticker::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'path', 'mime'])
            ->mapWithKeys(fn (Sticker $sticker): array => [$sticker->id => ['id' => $sticker->id, 'name' => $sticker->name, 'path' => $sticker->path, 'mime' => $sticker->mime]])
            ->all());
    }
}
