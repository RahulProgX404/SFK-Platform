<?php

/*
 * 受注書を読み込む (legacy 04 create-screen import): the rules that turn a read purchase order into order fields.
 */

use Modules\Orders\Domain\FaxImport\FaxImportRules;
use Modules\Orders\Domain\FaxImport\FaxOrderMapper;

function faxRules(): FaxImportRules
{
    return new FaxImportRules(
        'ハート封筒株式会社',
        ['古井' => 'カマス貼 1F・2F', '新田' => '2・5・6号機 190㎜巾まで', '金山' => '3号機 T240(245㎜巾まで)'],
        '金山',
        '3号機 T240(245㎜巾まで)',
        '社内',
        '封筒(別注)',
        '紙支給',
    );
}

/** @return array<string, mixed> */
function faxReading(array $overrides = []): array
{
    return array_replace_recursive([
        '発注情報' => ['発行元' => 'ハート封筒（株）大阪工場', '発注日' => '2026/8/18'],
        '赤マーク' => ['検印欄' => '26.8.18 古井（丸印）'],
        '赤丸番号付きデータ' => [
            '印刷名' => '株式会社サンプル 長3', '手配数' => '１０，１００ 枚（受注 10,000 ＋ 予備 100）', '納期' => '9/3(木) 直送・午前',
            '加工伝票番号' => 'K-12345', 'フタ仕様_折り返し寸法' => 'フタ開き／カド角、折り返し寸法 105mm、手書「8/30」「アバン便」',
        ],
        '製品仕様' => ['仕上寸法' => 'ヨコ 120 タテ 235 フタ 30'],
        '窓仕様' => ['窓' => [['番号' => '窓①', 'ヨコ' => '90', 'タテ' => '45', '左より' => '20', '底より' => '15']]],
        '口糊加工' => ['加工方法' => 'アラビア', '詳細' => '黄色'],
    ], $overrides);
}

it('accepts only purchase orders of the configured issuer, legal form and factory name ignored', function () {
    expect(FaxOrderMapper::isFromIssuer('ハート封筒（株）大阪工場', faxRules()))->toBeTrue()
        ->and(FaxOrderMapper::isFromIssuer('ハート封筒株式会社', faxRules()))->toBeTrue()
        ->and(FaxOrderMapper::isFromIssuer('サンプル封筒株式会社', faxRules()))->toBeFalse()
        ->and(FaxOrderMapper::isFromIssuer('', faxRules()))->toBeFalse();
});

it('finds the stamp in 検印欄, else lists the stamps seen in other fields', function () {
    expect(FaxOrderMapper::inspectionStamp(faxReading(), faxRules()))->toBe('古井');

    $unstamped = faxReading(['赤マーク' => ['検印欄' => '判読不能', '発注担当者欄' => '新田']]);
    expect(FaxOrderMapper::inspectionStamp($unstamped, faxRules()))->toBeNull()
        ->and(FaxOrderMapper::stampCandidates($unstamped, faxRules()))->toBe(['新田' => '発注担当者欄']);
});

it('maps the reading to the order like the legacy tool', function () {
    $mapping = FaxOrderMapper::map(faxReading(), '古井', faxRules(), new DateTimeImmutable('2026-08-20'));

    expect($mapping->errors)->toBe([])
        ->and($mapping->values)->toMatchArray([
            'due_date' => '2026-09-03',
            'completed_on' => '2026-09-02',
            'quantity' => '10100',
            'unit' => '枚',
            'product_name' => '古井　株式会社サンプル 長3',
            'delivered_on' => '2026-08-30',
            'plate_notes' => 'K-12345',
            'bag_schedule' => 'カマス貼 1F・2F',
            'flap_glue' => '黄色アラビア',
            'window_width' => '90.00',
            'window_height' => '45.00',
            'window_from_left' => '20.00',
            'window_from_bottom' => '15.00',
            'width' => '120.00',
            'height' => '235.00',
            'flap' => '30.00',
            'customer_name' => 'ハート封筒（株）大阪工場',
            'manufacturer_name' => '社内',
            'category1' => '封筒(別注)',
            'category2' => '紙支給',
        ])
        ->and(collect($mapping->fields)->pluck('label')->all())->toContain('品名', '数量', '口糊', '製袋予定規格');
});

it('gives the priority stamp its schedule and warns about unclear or unknown values', function () {
    $reading = faxReading([
        '赤マーク' => ['発注担当者欄' => '金山'],
        '赤丸番号付きデータ' => ['手配数' => '500 ケース'],
        '口糊加工' => ['加工方法' => 'テープスチック', '詳細' => '不鮮明'],
    ]);
    $mapping = FaxOrderMapper::map($reading, '古井', faxRules(), new DateTimeImmutable('2026-08-20'));
    $warnings = collect($mapping->fields)->pluck('warning', 'field');

    expect($mapping->values['bag_schedule'])->toBe('3号機 T240(245㎜巾まで)')
        ->and($mapping->values['flap_glue'])->toBe('両面テープ10㎜')
        ->and($warnings['flap_glue'])->toContain('暫定で「両面テープ10㎜」')
        // 「ケース」 is not a 単位 option: left blank with a warning
        ->and($mapping->values['unit'])->toBe('')
        ->and($warnings['unit'])->toBe('単位「ケース」は選択肢にないため設定していません');
});

it('reports what could not be read', function () {
    $mapping = FaxOrderMapper::map(['発注情報' => ['発行元' => 'ハート封筒株式会社']], '古井', faxRules(), new DateTimeImmutable('2026-08-20'));

    expect($mapping->errors)->toBe([
        '納期を読み取れませんでした。',
        '数量を読み取れませんでした（0より大きい必要があります）。',
        '単位を読み取れませんでした。',
        '印刷名を読み取れませんでした。',
    ])->and($mapping->values['product_name'])->toBe('古井')
        ->and($mapping->values['flap_glue'])->toBe('口糊無');
});

it('reads legacy date notations', function () {
    expect(FaxOrderMapper::date('2026年9月3日(木)', 2025))->toBe('2026-09-03')
        ->and(FaxOrderMapper::date('26.9.3', 2025))->toBe('2026-09-03')
        ->and(FaxOrderMapper::date('９/３', 2026))->toBe('2026-09-03')
        ->and(FaxOrderMapper::date('空欄', 2026))->toBe('')
        ->and(FaxOrderMapper::date('2/30', 2026))->toBe('');
});

it('puts a handwritten date long after 納期 into the year before', function () {
    $reading = faxReading(['赤丸番号付きデータ' => ['納期' => '2027/1/10', 'フタ仕様_折り返し寸法' => '手書「12/20」']]);

    expect(FaxOrderMapper::map($reading, '古井', faxRules(), new DateTimeImmutable('2026-12-01'))->values['delivered_on'])->toBe('2026-12-20');
});
