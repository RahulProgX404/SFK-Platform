<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Partners;

use Modules\Core\Domain\Concerns\HasOptions;

/**
 * グループ名 (SYSTEM_BRAIN APP_01). The legacy app has 9 disabled options (the names also found in 分類, C-12); records
 * taken over from the legacy data keep them, but they are not offered for new choices (activeOptions()), like the
 * legacy system's disabled options.
 */
enum PartnerGroup: string
{
    use HasOptions;

    case G1 = '（１）大日本印刷関連';
    case G2 = '（２）凸版印刷関連';
    case G3 = '（３）民間・印刷会社';
    case G4 = '（４）民間・その他';
    case G5 = '（５）公共機関・入札';
    case G6 = '（６）ネット販売';
    case G7 = '（７）直需販売';
    case G8 = '（８）その他売上';

    // disabled legacy options
    case Dnp = 'DNP';
    case Toppan = '凸版';
    case AdPrinting = 'アド印刷';
    case Yamagata = 'ヤマガタ';
    case Tagaya = 'タガヤ';
    case Reito = '麗都商事';
    case OsakaShokoShinkin = '大阪商工信金';
    case NishiNihonJutaku = '西日本住宅';
    case HigashiNihonJutaku = '東日本住宅';

    public function isRetired(): bool
    {
        return ! str_starts_with($this->value, '（');
    }

    /**
     * Choices of the form: the active options, plus the record's own value when it is a disabled one.
     *
     * @return array<string, string>
     */
    public static function activeOptions(?string $current = null): array
    {
        return array_filter(
            self::options(),
            fn (string $value): bool => ! self::from($value)->isRetired() || $value === $current,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return list<string> */
    public static function retiredValues(): array
    {
        return array_values(array_map(
            fn (self $group): string => $group->value,
            array_filter(self::cases(), fn (self $group): bool => $group->isRetired()),
        ));
    }
}
