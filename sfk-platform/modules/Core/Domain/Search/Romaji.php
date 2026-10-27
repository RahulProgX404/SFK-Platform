<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Search;

/**
 * Romaji → hiragana for the list search, the way Japanese input methods read it (Hepburn, Kunrei and the usual
 * keyboard spellings: shi / si, tsu / tu, fu / hu, ja / zi, nn / n' → ん, a doubled consonant → っ, l / x → small kana,
 * - → ー). Digits and other signs are kept. A word with letters that are not romaji gives null.
 */
final class Romaji
{
    private const TABLE = [
        'a' => 'あ', 'i' => 'い', 'u' => 'う', 'e' => 'え', 'o' => 'お',
        'ka' => 'か', 'ki' => 'き', 'ku' => 'く', 'ke' => 'け', 'ko' => 'こ', 'ca' => 'か', 'cu' => 'く', 'co' => 'こ', 'qa' => 'くぁ', 'qi' => 'くぃ', 'qe' => 'くぇ', 'qo' => 'くぉ',
        'kya' => 'きゃ', 'kyi' => 'きぃ', 'kyu' => 'きゅ', 'kye' => 'きぇ', 'kyo' => 'きょ',
        'ga' => 'が', 'gi' => 'ぎ', 'gu' => 'ぐ', 'ge' => 'げ', 'go' => 'ご', 'gya' => 'ぎゃ', 'gyi' => 'ぎぃ', 'gyu' => 'ぎゅ', 'gye' => 'ぎぇ', 'gyo' => 'ぎょ',
        'sa' => 'さ', 'si' => 'し', 'shi' => 'し', 'su' => 'す', 'se' => 'せ', 'so' => 'そ', 'ci' => 'し', 'ce' => 'せ',
        'sha' => 'しゃ', 'shu' => 'しゅ', 'she' => 'しぇ', 'sho' => 'しょ', 'sya' => 'しゃ', 'syi' => 'しぃ', 'syu' => 'しゅ', 'sye' => 'しぇ', 'syo' => 'しょ',
        'za' => 'ざ', 'zi' => 'じ', 'ji' => 'じ', 'zu' => 'ず', 'ze' => 'ぜ', 'zo' => 'ぞ',
        'ja' => 'じゃ', 'ju' => 'じゅ', 'je' => 'じぇ', 'jo' => 'じょ', 'jya' => 'じゃ', 'jyi' => 'じぃ', 'jyu' => 'じゅ', 'jye' => 'じぇ', 'jyo' => 'じょ',
        'zya' => 'じゃ', 'zyi' => 'じぃ', 'zyu' => 'じゅ', 'zye' => 'じぇ', 'zyo' => 'じょ',
        'ta' => 'た', 'ti' => 'ち', 'chi' => 'ち', 'tu' => 'つ', 'tsu' => 'つ', 'te' => 'て', 'to' => 'と',
        'cha' => 'ちゃ', 'chu' => 'ちゅ', 'che' => 'ちぇ', 'cho' => 'ちょ', 'tya' => 'ちゃ', 'tyi' => 'ちぃ', 'tyu' => 'ちゅ', 'tye' => 'ちぇ', 'tyo' => 'ちょ',
        'cya' => 'ちゃ', 'cyi' => 'ちぃ', 'cyu' => 'ちゅ', 'cye' => 'ちぇ', 'cyo' => 'ちょ',
        'tsa' => 'つぁ', 'tsi' => 'つぃ', 'tse' => 'つぇ', 'tso' => 'つぉ', 'tha' => 'てゃ', 'thi' => 'てぃ', 'thu' => 'てゅ', 'the' => 'てぇ', 'tho' => 'てょ',
        'twu' => 'とぅ',
        'da' => 'だ', 'di' => 'ぢ', 'du' => 'づ', 'de' => 'で', 'do' => 'ど',
        'dya' => 'ぢゃ', 'dyi' => 'ぢぃ', 'dyu' => 'ぢゅ', 'dye' => 'ぢぇ', 'dyo' => 'ぢょ', 'dha' => 'でゃ', 'dhi' => 'でぃ', 'dhu' => 'でゅ', 'dhe' => 'でぇ', 'dho' => 'でょ',
        'dwu' => 'どぅ',
        'na' => 'な', 'ni' => 'に', 'nu' => 'ぬ', 'ne' => 'ね', 'no' => 'の', 'nya' => 'にゃ', 'nyi' => 'にぃ', 'nyu' => 'にゅ', 'nye' => 'にぇ', 'nyo' => 'にょ',
        'ha' => 'は', 'hi' => 'ひ', 'hu' => 'ふ', 'fu' => 'ふ', 'he' => 'へ', 'ho' => 'ほ',
        'hya' => 'ひゃ', 'hyi' => 'ひぃ', 'hyu' => 'ひゅ', 'hye' => 'ひぇ', 'hyo' => 'ひょ',
        'fa' => 'ふぁ', 'fi' => 'ふぃ', 'fe' => 'ふぇ', 'fo' => 'ふぉ', 'fya' => 'ふゃ', 'fyu' => 'ふゅ', 'fyo' => 'ふょ',
        'ba' => 'ば', 'bi' => 'び', 'bu' => 'ぶ', 'be' => 'べ', 'bo' => 'ぼ', 'bya' => 'びゃ', 'byi' => 'びぃ', 'byu' => 'びゅ', 'bye' => 'びぇ', 'byo' => 'びょ',
        'pa' => 'ぱ', 'pi' => 'ぴ', 'pu' => 'ぷ', 'pe' => 'ぺ', 'po' => 'ぽ', 'pya' => 'ぴゃ', 'pyi' => 'ぴぃ', 'pyu' => 'ぴゅ', 'pye' => 'ぴぇ', 'pyo' => 'ぴょ',
        'ma' => 'ま', 'mi' => 'み', 'mu' => 'む', 'me' => 'め', 'mo' => 'も', 'mya' => 'みゃ', 'myi' => 'みぃ', 'myu' => 'みゅ', 'mye' => 'みぇ', 'myo' => 'みょ',
        'ya' => 'や', 'yu' => 'ゆ', 'ye' => 'いぇ', 'yo' => 'よ',
        'ra' => 'ら', 'ri' => 'り', 'ru' => 'る', 're' => 'れ', 'ro' => 'ろ', 'rya' => 'りゃ', 'ryi' => 'りぃ', 'ryu' => 'りゅ', 'rye' => 'りぇ', 'ryo' => 'りょ',
        'wa' => 'わ', 'wi' => 'うぃ', 'we' => 'うぇ', 'wo' => 'を', 'wha' => 'うぁ', 'whi' => 'うぃ', 'whe' => 'うぇ', 'who' => 'うぉ',
        'va' => 'ゔぁ', 'vi' => 'ゔぃ', 'vu' => 'ゔ', 've' => 'ゔぇ', 'vo' => 'ゔぉ',
        'xa' => 'ぁ', 'xi' => 'ぃ', 'xu' => 'ぅ', 'xe' => 'ぇ', 'xo' => 'ぉ', 'la' => 'ぁ', 'li' => 'ぃ', 'lu' => 'ぅ', 'le' => 'ぇ', 'lo' => 'ぉ',
        'xya' => 'ゃ', 'xyu' => 'ゅ', 'xyo' => 'ょ', 'lya' => 'ゃ', 'lyu' => 'ゅ', 'lyo' => 'ょ', 'xtu' => 'っ', 'ltu' => 'っ', 'xtsu' => 'っ', 'ltsu' => 'っ',
        'xwa' => 'ゎ', 'lwa' => 'ゎ', 'xka' => 'ゕ', 'xke' => 'ゖ', 'nn' => 'ん', "n'" => 'ん', 'xn' => 'ん',
    ];

    private const VOWELS = 'aiueo';

    public static function toHiragana(string $word): ?string
    {
        $word = strtolower($word);
        if (preg_match('/[a-z]/', $word) !== 1 || preg_match('/[^\x20-\x7e]/', $word) === 1) {
            return null;
        }

        $kana = '';
        $length = strlen($word);
        for ($i = 0; $i < $length;) {
            $char = $word[$i];
            $next = $word[$i + 1] ?? '';

            if (! ctype_alpha($char)) {
                $kana .= $char === '-' ? 'ー' : $char;
                $i++;

                continue;
            }

            // nn before a vowel is ん + な行 (konnichiha → こんにちは); otherwise nn is ん (honnya → ほんや)
            if ($char === 'n' && $next === 'n') {
                $after = $word[$i + 2] ?? '';
                $kana .= 'ん';
                $i += $after !== '' && str_contains(self::VOWELS, $after) ? 1 : 2;

                continue;
            }

            foreach ([4, 3, 2, 1] as $size) {
                $part = substr($word, $i, $size);
                if (strlen($part) === $size && isset(self::TABLE[$part])) {
                    $kana .= self::TABLE[$part];
                    $i += $size;

                    continue 2;
                }
            }

            // ん before a consonant or at the end; a doubled consonant (and t before ch) → っ
            if ($char === 'n' && ($next === '' || ! str_contains(self::VOWELS.'y', $next))) {
                $kana .= 'ん';
                $i++;

                continue;
            }
            if ($next !== '' && ! str_contains(self::VOWELS.'n', $char) && ($next === $char || ($char === 't' && $next === 'c'))) {
                $kana .= 'っ';
                $i++;

                continue;
            }

            return null;
        }

        return $kana;
    }
}
