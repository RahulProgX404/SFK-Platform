<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Search;

use Normalizer;

/**
 * The words of the list search and the forms they are looked for in, so that 「ﾌｳﾄｳ」, 「フウトウ」 and 「ふうとう」 find
 * each other, as do 「ＡＢＣ」 and 「abc」, and a word typed in romaji is also looked for in kana (「fuutou」 →
 * 「ふうとう」 / 「フウトウ」).
 *
 * - fold(): NFKC (全角英数字 → 半角, 半角カナ → 全角, ① → 1) and lower case — what the database compares the columns
 *   in (SQL function sfk_search_norm, trigram-indexed); kana are not folded there (too slow), so variants() gives a
 *   word in hiragana and in katakana.
 * - shortVariants(): a word of one or two characters cannot use the trigram index, so it is compared with the column
 *   as stored (lower case only, as fast as a plain search) in every width and kana form.
 */
final class SearchText
{
    /** Words this long or longer are compared on the folded, indexed form. */
    public const INDEXED_LENGTH = 3;

    /** NFKC and lower case; keep in step with the SQL function (SearchQuery::postgresFunction()). */
    public static function fold(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_KC);

        return mb_strtolower($text === false ? '' : $text, 'UTF-8');
    }

    /** fold() with katakana as hiragana: one form for comparisons made in PHP (e.g. the labels of options). */
    public static function normalize(string $text): string
    {
        return self::hiragana(self::fold($text));
    }

    /**
     * The words of a search (spaces, also 全角スペース, separate them); each must be found.
     *
     * @return list<string>
     */
    public static function words(string $search): array
    {
        return array_values(array_filter(
            preg_split('/\s+/u', self::fold($search)) ?: [],
            fn (string $word): bool => $word !== '',
        ));
    }

    /**
     * The folded forms a word is looked for in: in hiragana and in katakana and, when it is romaji, its kana too.
     *
     * @return list<string>
     */
    public static function variants(string $word): array
    {
        $word = self::fold($word);
        $forms = [$word];
        if (($kana = Romaji::toHiragana($word)) !== null) {
            $forms[] = $kana;
        }

        $variants = [];
        foreach ($forms as $form) {
            array_push($variants, $form, self::hiragana($form), self::katakana($form));
        }

        return self::unique($variants);
    }

    /**
     * The stored forms a short word may have (compared with the column in lower case only): every variant() also in
     * 全角英数字 and in 半角カナ.
     *
     * @return list<string>
     */
    public static function shortVariants(string $word): array
    {
        $variants = [];
        foreach (self::variants($word) as $form) {
            array_push($variants, $form, mb_convert_kana($form, 'A', 'UTF-8'), mb_convert_kana(self::katakana($form), 'k', 'UTF-8'));
        }

        return self::unique($variants);
    }

    public static function isIndexed(string $word): bool
    {
        return mb_strlen(self::fold($word), 'UTF-8') >= self::INDEXED_LENGTH;
    }

    /** ァ (U+30A1) … ヶ (U+30F6) → ぁ (U+3041) … ゖ (U+3096) */
    public static function hiragana(string $text): string
    {
        return (string) preg_replace_callback('/[\x{30A1}-\x{30F6}]/u', fn (array $m): string => (string) mb_chr((int) mb_ord($m[0], 'UTF-8') - 0x60, 'UTF-8'), $text);
    }

    /** ぁ (U+3041) … ゖ (U+3096) → ァ (U+30A1) … ヶ (U+30F6) */
    public static function katakana(string $text): string
    {
        return (string) preg_replace_callback('/[\x{3041}-\x{3096}]/u', fn (array $m): string => (string) mb_chr((int) mb_ord($m[0], 'UTF-8') + 0x60, 'UTF-8'), $text);
    }

    /**
     * @param  list<string>  $forms
     * @return list<string>
     */
    private static function unique(array $forms): array
    {
        return array_values(array_unique(array_filter($forms, fn (string $form): bool => $form !== '')));
    }
}
