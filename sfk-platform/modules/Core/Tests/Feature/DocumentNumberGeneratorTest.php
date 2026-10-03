<?php

use Modules\Core\Application\Numbering\DocumentNumberGenerator;

it('issues sequential job numbers in the kintone format (BR-OR-01)', function () {
    $numbers = app(DocumentNumberGenerator::class);

    expect($numbers->peek('order'))->toBe('sfk-0000001')
        ->and($numbers->next('order'))->toBe('sfk-0000001')
        ->and($numbers->next('order'))->toBe('sfk-0000002')
        ->and($numbers->next('invoice'))->toBe('0000001');
});

it('continues after migrated kintone numbers', function () {
    $numbers = app(DocumentNumberGenerator::class);

    $numbers->ensureNextAtLeast('delivery_note', 4568);
    $numbers->ensureNextAtLeast('delivery_note', 10); // never moves backwards

    expect($numbers->next('delivery_note'))->toBe('0004568');
});

it('rejects unknown sequences', function () {
    app(DocumentNumberGenerator::class)->next('unknown');
})->throws(InvalidArgumentException::class);
