<?php

/*
 * 09. 見積書管理 (SYSTEM_BRAIN APP_09): a quotation takes 担当者 from the customer's 自社担当者, totals its quantity tiers
 * (金額 = 単価 × 枚数, 見積金額(税抜) = Σ 金額), keeps 見積書番号 unique and lists in 見積一覧.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Filament\Resources\Quotations\Pages\CreateQuotation;
use Modules\Orders\Filament\Resources\Quotations\Pages\ListQuotations;
use Modules\Orders\Filament\Resources\Quotations\Pages\ViewQuotation;
use Modules\Orders\Models\Quotation;

beforeEach(function () {
    Filament::setCurrentPanel('erp');
    $this->admin = User::factory()->superAdmin()->create(['name' => '管理者']);
    $this->sales = User::factory()->create(['name' => '営業 花子']);
    $this->customer = Partner::query()->create(['name' => '株式会社見積先', 'short_name' => 'ミツモリ', 'is_customer' => true, 'code' => 'C100']);
    $this->customer->staff()->attach($this->sales);
});

it('registers 09. 見積書管理 from the code', function () {
    expect((new AppDiscovery([base_path('modules')]))->definitions())->toHaveKey('quotation', '09. 見積書管理');
});

it('enters a quotation with its tiers and the customer\'s 担当者', function () {
    $this->actingAs($this->admin);
    $undoFake = Repeater::fake();

    Livewire::test(CreateQuotation::class)
        ->assertSchemaStateSet(['size' => '左右　× 天地'])
        ->fillForm(['customer_id' => $this->customer->id])
        ->assertSchemaStateSet(['assignees' => [(string) $this->sales->id]])
        ->fillForm(['quoted_on' => '2026-10-21', 'quote_number' => 'Q-0001', 'product_name' => '長3封筒', 'paper' => 'クラフト70', 'lines' => [
            ['sheets' => '1000', 'unit_price' => '12.5'],
            ['sheets' => '5000', 'unit_price' => '9.85'],
        ]])
        ->call('create')
        ->assertHasNoFormErrors();
    $undoFake();

    $quotation = Quotation::query()->sole();
    // 12,500 + 49,250
    expect($quotation->total)->toBe(61750)->and($quotation->assignees->pluck('name')->all())->toBe(['営業 花子'])->and($quotation->fileName())->toBe('Q-0001_株式会社見積先');

    Livewire::test(ViewQuotation::class, ['record' => $quotation->getRouteKey()])
        ->assertSeeInOrder(['見積書番号', 'Q-0001', '見積日付', '2026-10-21', '得意先名', '株式会社見積先', '担当者', '営業 花子', '品名', '長3封筒', '見積金額(税抜)', '¥61,750',
            '紙', 'クラフト70', '見積内容', '1,000', '12.5', '¥12,500', '5,000', '9.85', '¥49,250']);

    // 見積書番号 is unique
    Livewire::test(CreateQuotation::class)->fillForm(['quoted_on' => '2026-10-22', 'quote_number' => 'Q-0001'])->call('create')->assertHasFormErrors(['quote_number' => 'unique']);

    Livewire::test(ListQuotations::class)
        ->assertSee('見積一覧')
        ->assertCanSeeTableRecords([$quotation])
        ->searchTable('ミツモリ')
        ->assertCanSeeTableRecords([$quotation])
        ->sortTable('customer_id');
});
