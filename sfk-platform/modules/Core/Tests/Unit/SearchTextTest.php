<?php

/*
 * The list search compares one form of the text: 全角 / 半角 (NFKC), upper / lower case and カタカナ / ひらがな fold
 * together; a word typed in romaji is also looked for in kana.
 */

use Modules\Core\Domain\Search\Romaji;
use Modules\Core\Domain\Search\SearchText;

it('folds width, case and katakana into one form', function (string $text, string $form) {
    expect(SearchText::normalize($text))->toBe($form);
})->with([
    'full-width latin' => ['ＡＢＣ－１２３', 'abc-123'],
    'upper case' => ['Shiraishi', 'shiraishi'],
    'katakana' => ['フウトウ', 'ふうとう'],
    'half-width katakana with dakuten' => ['ﾅｶﾞｻｷ', 'ながさき'],
    'ヴ and small ヶ' => ['ヴァイオリン ヶ', 'ゔぁいおりん ゖ'],
    'circled digit and full-width space' => ['①　長３封筒', '1 長3封筒'],
    'kanji and ー stay' => ['封筒センター', '封筒せんたー'],
]);

it('splits the search into words at any space', function () {
    expect(SearchText::words(' 長３　ﾌｳﾄｳ  ABC '))->toBe(['長3', 'フウトウ', 'abc']);
});

it('reads romaji as hiragana like an input method', function (string $romaji, ?string $kana) {
    expect(Romaji::toHiragana($romaji))->toBe($kana);
})->with([
    ['fuutou', 'ふうとう'],
    ['shiraishi', 'しらいし'],
    ['siraisi', 'しらいし'],
    ['tsutsumi', 'つつみ'],
    ['kitte', 'きって'],
    ['matcha', 'まっちゃ'],
    ['konnichiha', 'こんにちは'],
    ['honnya', 'ほんや'],
    ["kin'en", 'きんえん'],
    ['shinbun', 'しんぶん'],
    ['kyouto', 'きょうと'],
    ['jidousha', 'じどうしゃ'],
    ['se-ru', 'せーる'],
    ['kaku2', 'かく2'],
    ['box', null],
    ['abc', null],
    ['123', null],
    ['ふうとう', null],
]);

it('folds only width and case for the indexed column form', function () {
    expect(SearchText::fold('ＡＢＣ ﾌｳﾄｳ フウトウ'))->toBe('abc フウトウ フウトウ');
});

it('looks for a word in hiragana and katakana, a romaji word in kana too', function () {
    expect(SearchText::variants('Fuutou'))->toBe(['fuutou', 'ふうとう', 'フウトウ'])
        ->and(SearchText::variants('ＡＢＣ'))->toBe(['abc'])
        ->and(SearchText::variants('ﾌｳﾄｳ'))->toBe(['フウトウ', 'ふうとう'])
        ->and(SearchText::variants('1000'))->toBe(['1000']);
});

it('looks for a short word in every stored width and kana form', function () {
    expect(SearchText::isIndexed('封筒'))->toBeFalse()
        ->and(SearchText::isIndexed('ﾌｳﾄｳ'))->toBeTrue()
        ->and(SearchText::shortVariants('封筒'))->toBe(['封筒'])
        ->and(SearchText::shortVariants('Ab'))->toBe(['ab', 'ａｂ'])
        ->and(SearchText::shortVariants('ヤマ'))->toBe(['ヤマ', 'ﾔﾏ', 'やま']);
});
