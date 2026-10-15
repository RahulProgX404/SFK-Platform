<?php

use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordLayout;

it('maps legacy field widths to a 12-column row', function () {
    // 01. 取引先マスタ header row: 取引先コード(128) | 親会社(554) | 取引先名(554)
    expect(RecordLayout::spans([128, 554, 554]))->toBe([1, 4, 4])
        // customer address row incl. 仕入先住所も同様
        ->and(RecordLayout::spans([193, 591, 193, 193, 148]))->toBe([2, 5, 2, 2, 1]);
});

it('never puts more than 12 columns in a row', function () {
    // 基本情報 tax row: six selects (196) and 電子請求 (94) are 13 columns at full size
    $spans = RecordLayout::spans([196, 196, 196, 196, 196, 196, 94]);

    expect(array_sum($spans))->toBe(12)
        ->and(min($spans))->toBeGreaterThanOrEqual(1);
});

it('shows a tab section only while its tab is selected', function () {
    $tabbed = RecordLayout::tabbed(['basic' => '基本情報', 'supplier' => '仕入先情報'], []);
    $basic = RecordLayout::tabPanel('basic', Section::make(), selected: true);
    $supplier = RecordLayout::tabPanel('supplier', Section::make()->extraAttributes(['class' => 'kt-record']));

    expect($tabbed->getExtraAttributes())->toMatchArray(['x-data' => "{ tab: 'basic' }"])
        ->and($basic->getExtraAttributes())->toBe(['x-show' => "tab === 'basic'"])
        ->and($supplier->getExtraAttributes())->toEqual(['class' => 'kt-record', 'x-show' => "tab === 'supplier'", 'x-cloak' => true]);
});

it('rejects tab keys that could break out of the Alpine expression', function () {
    RecordLayout::tabPanel("x' || alert(1) || '", Section::make());
})->throws(InvalidArgumentException::class);
