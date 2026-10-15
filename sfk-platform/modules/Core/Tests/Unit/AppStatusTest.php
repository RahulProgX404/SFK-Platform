<?php

/*
 * App status pipeline (permission.md "Permission hierarchy").
 */

use Modules\Core\Domain\Apps\AppStatus;

/** @return list<string> "from>to" pairs allowed */
function allowedMoves(bool $superAdmin): array
{
    $moves = [];
    foreach (AppStatus::cases() as $from) {
        foreach (AppStatus::cases() as $to) {
            if ($from->canMoveTo($to, $superAdmin)) {
                $moves[] = "{$from->value}>{$to->value}";
            }
        }
    }

    return $moves;
}

it('allows super admins exactly the documented moves', function () {
    expect(allowedMoves(superAdmin: true))->toEqualCanonicalizing([
        'developing>testing',
        'testing>ready',
        'ready>published',
        'maintenance>published',
        'stopped>published',
        'published>maintenance',
        'published>stopped',
    ]);
});

it('allows admins only 準備完了 → 公開中 and 公開中 ⇄ 停止中', function () {
    expect(allowedMoves(superAdmin: false))->toEqualCanonicalizing([
        'ready>published',
        'published>stopped',
        'stopped>published',
    ]);
});

it('never moves anything back to 開発中, nor between メンテナンス中 and 停止中', function () {
    foreach ([true, false] as $superAdmin) {
        foreach (AppStatus::cases() as $from) {
            expect($from->canMoveTo(AppStatus::Developing, $superAdmin))->toBeFalse();
        }
        expect(AppStatus::Maintenance->canMoveTo(AppStatus::Stopped, $superAdmin))->toBeFalse()
            ->and(AppStatus::Stopped->canMoveTo(AppStatus::Maintenance, $superAdmin))->toBeFalse();
    }
});

it('describes who may use an app in each status', function () {
    expect(AppStatus::Developing->isSuperAdminOnly())->toBeTrue()
        ->and(AppStatus::Testing->isSuperAdminOnly())->toBeTrue()
        ->and(AppStatus::Ready->isSuperAdminOnly())->toBeFalse()
        ->and(AppStatus::Published->isOpenToEmployees())->toBeTrue()
        ->and(AppStatus::Maintenance->isOpenToEmployees())->toBeTrue()
        ->and(AppStatus::Maintenance->isReadOnlyForEmployees())->toBeTrue()
        ->and(AppStatus::Ready->isOpenToEmployees())->toBeFalse()
        ->and(AppStatus::Stopped->isOpenToEmployees())->toBeFalse();
});
