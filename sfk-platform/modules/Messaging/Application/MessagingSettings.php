<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Messaging\Models\MessagingSetting;
use WeakMap;

/**
 * The company's messenger policy, set by administrators in メッセージ管理 (table messaging_settings, read through
 * ReferenceCache) over the defaults of config/messaging. `ensure()` lays the stored values over config('messaging.*')
 * once per request, so the services keep reading config.
 */
final class MessagingSettings
{
    /** key => [config path, type, label] */
    public const DEFINITIONS = [
        'edit_window_minutes' => ['messaging.edit_window_minutes', 'minutes', '編集できる期間（分）'],
        'unsend_window_minutes' => ['messaging.unsend_window_minutes', 'minutes', '送信取消できる期間（分）'],
        'group_creators' => ['messaging.group_creators', 'who', 'グループを作成できる人'],
        'channel_creators' => ['messaging.channel_creators', 'who', 'チャンネルを作成できる人'],
        'max_attachment_mb' => ['messaging.attachments.max_kb', 'mb', '添付ファイルの上限（MB）'],
        'retention_days' => ['messaging.retention_days', 'days', 'メッセージの保存期間（日）'],
        'read_receipts' => ['messaging.read_receipts', 'bool', '既読を表示する'],
        'presence' => ['messaging.presence', 'bool', 'オンライン状態を表示する'],
    ];

    /** @var WeakMap<object, true>|null */
    private static ?WeakMap $applied = null;

    /** Lays the stored settings over the config (once per request). */
    public static function ensure(): void
    {
        self::$applied ??= new WeakMap;
        $request = request();
        if (isset(self::$applied[$request])) {
            return;
        }
        self::$applied[$request] = true;

        foreach (self::stored() as $key => $value) {
            if (isset(self::DEFINITIONS[$key])) {
                config([self::DEFINITIONS[$key][0] => self::toConfig($key, $value)]);
            }
        }
    }

    /**
     * The values in force, in the form of the settings page.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        self::ensure();
        $values = [];
        foreach (self::DEFINITIONS as $key => [$path, $type]) {
            $config = config($path);
            $values[$key] = match ($type) {
                'mb' => (int) round(((int) $config) / 1024),
                'bool' => (bool) ($config ?? true),
                'who' => (string) ($config ?? 'everyone'),
                default => $config === null ? null : (int) $config,
            };
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string> the keys that changed
     */
    public static function save(User $actor, array $input): array
    {
        $current = self::values();
        $changed = [];
        foreach (self::DEFINITIONS as $key => [, $type]) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = self::validate($key, $type, $input[$key]);
            if ($value === $current[$key]) {
                continue;
            }
            MessagingSetting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
            $changed[] = $key;
        }

        if ($changed !== []) {
            ReferenceCache::bump();
            self::$applied = null;
            self::ensure();
            // the sessionless pulse cannot read the database: the presence policy goes to the cache too
            Cache::forever('messaging:presence-policy', (bool) config('messaging.presence', true));
            MessagingAudit::record($actor, 'settings.updated', null, ['keys' => $changed]);
        }

        return $changed;
    }

    /** @return array<string, mixed> key => stored value */
    private static function stored(): array
    {
        return ReferenceCache::remember('messaging-settings', fn (): array => MessagingSetting::query()->get(['key', 'value'])
            ->mapWithKeys(fn (MessagingSetting $setting): array => [$setting->key => is_array($setting->value) ? ($setting->value['v'] ?? null) : null])
            ->all());
    }

    private static function toConfig(string $key, mixed $value): mixed
    {
        return match (self::DEFINITIONS[$key][1]) {
            'mb' => max(1, (int) $value) * 1024,
            'bool' => (bool) $value,
            'who' => $value === 'administrators' ? 'administrators' : 'everyone',
            default => $value === null ? null : (int) $value,
        };
    }

    private static function validate(string $key, string $type, mixed $value): mixed
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$key => $message]);

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'who' => in_array($value, ['everyone', 'administrators'], true) ? $value : $fail('選択肢から選んでください。'),
            'mb' => is_numeric($value) && (int) $value >= 1 && (int) $value <= 100 ? (int) $value : $fail('1〜100MBで入力してください。'),
            'minutes' => $value === null || $value === '' ? null : (is_numeric($value) && (int) $value >= 0 && (int) $value <= 525600 ? (int) $value : $fail('0〜525600分で入力してください。空欄は無期限です。')),
            'days' => $value === null || $value === '' ? null : (is_numeric($value) && (int) $value >= 30 ? (int) $value : $fail('30日以上で入力してください。空欄は無期限です。')),
            default => $value,
        };
    }
}
