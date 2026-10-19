<?php

/*
 * 受注書を読み込む (legacy 04 create-screen import): finding the stamp (検印欄, another field, a second reading), the
 * partner lookups, and the Claude reader's answer handling — without calling the API.
 */

use Modules\MasterData\Models\Partner;
use Modules\Orders\Application\FaxImport\ClaudeFaxOrderReader;
use Modules\Orders\Application\FaxImport\FaxDocument;
use Modules\Orders\Application\FaxImport\FaxImportFailed;
use Modules\Orders\Application\FaxImport\FaxOrderImport;
use Modules\Orders\Application\FaxImport\FaxOrderReader;
use Modules\Orders\Domain\FaxImport\FaxImportRules;

/** A reader returning fixed readings; `$reread` is null when a second reading must not happen. */
function fakeFaxReader(array $reading, ?array $reread = null): FaxOrderReader
{
    return new class($reading, $reread) implements FaxOrderReader
    {
        public int $rereads = 0;

        public function __construct(private array $reading, private ?array $reread) {}

        public function read(FaxDocument $document): array
        {
            return $this->reading;
        }

        public function rereadInspectionStamp(FaxDocument $document, array $firstReading, array $candidates): array
        {
            $this->rereads++;

            return $this->reread ?? throw new LogicException('no second reading expected');
        }
    };
}

function importFax(FaxOrderReader $reader)
{
    return (new FaxOrderImport($reader))->import(
        new FaxDocument(base64_encode('png'), 'image/png', 'fax.png'),
        FaxImportRules::fromConfig((array) config('orders.fax_import')),
        new DateTimeImmutable('2026-10-19'),
    );
}

beforeEach(function () {
    $this->heart = Partner::query()->create(['name' => 'ハート封筒株式会社', 'short_name' => 'ハート', 'is_customer' => true]);
});

it('takes the one stamp seen in another field when 検印欄 has none', function () {
    $result = importFax(fakeFaxReader([
        '発注情報' => ['発行元' => 'ハート封筒株式会社', '発注担当者' => '梅村'],
        '赤マーク' => ['検印欄' => '空欄'],
    ]));

    expect($result->stamp)->toBe('梅村')
        ->and($result->stampSource)->toBe('発注情報.発注担当者')
        ->and($result->values['customer_id'])->toBe($this->heart->id)
        ->and($result->values['bag_schedule'])->toBe('3号機 T240(245㎜巾まで)')
        // 仕入先(製造場所) 「社内」 is not in the master: left empty with a warning
        ->and($result->values)->not->toHaveKey('manufacturer_id')
        ->and(collect($result->fields)->firstWhere('field', 'manufacturer_name')['warning'])->toBe('取引先マスタ（仕入先）に見つかりません');
});

it('reads 検印欄 again when no stamp can be told, and gives up with the legacy message', function () {
    $first = ['発注情報' => ['発行元' => 'ハート封筒株式会社'], '赤マーク' => ['検印欄' => '']];
    $reader = fakeFaxReader($first, [...$first, '赤マーク' => ['検印欄' => '新田']]);
    $result = importFax($reader);

    expect($result->stamp)->toBe('新田')->and($result->stampSource)->toBe('検印欄（再解析）')->and($reader->rereads)->toBe(1);

    expect(fn () => importFax(fakeFaxReader($first, $first)))
        ->toThrow(FaxImportFailed::class, '検印欄から得意先（古井・新田・梅村・ハ田・金山）を特定できませんでした。画像が鮮明かどうか確認のうえ、再度お試しください。');
});

it('asks for the API key and reads the JSON answer of the model', function () {
    expect(fn () => (new ClaudeFaxOrderReader(['api_key' => '']))->read(new FaxDocument('', 'image/png', 'fax.png')))
        ->toThrow(FaxImportFailed::class, 'AIのAPIキーが設定されていません。管理者に .env の ANTHROPIC_API_KEY を設定してもらってください。');

    expect(ClaudeFaxOrderReader::json("```json\n{\"件名\": \"封筒\"}\n```"))->toBe(['件名' => '封筒'])
        ->and(ClaudeFaxOrderReader::json('読み取り結果: {"件名": "封筒"} 以上'))->toBe(['件名' => '封筒'])
        ->and(fn () => ClaudeFaxOrderReader::json('読めません'))->toThrow(FaxImportFailed::class, 'AIの応答をJSONとして解釈できませんでした。');
});

it('recognises the media type of an upload like the legacy tool', function () {
    expect(FaxDocument::fromContents('x', 'application/pdf', 'a.bin')->isPdf())->toBeTrue()
        ->and(FaxDocument::fromContents('x', 'image/jpg', 'a.jpg')->mediaType)->toBe('image/jpeg')
        ->and(FaxDocument::fromContents('x', null, 'scan.webp')->mediaType)->toBe('image/webp')
        ->and(FaxDocument::fromContents('x', 'application/octet-stream', 'scan')->mediaType)->toBe('image/jpeg');
});
