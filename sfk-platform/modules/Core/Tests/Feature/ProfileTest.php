<?php

/*
 * プロフィール (profile icon of every dashboard): own details; 氏名・フリガナ・性別・住所・連絡先 editable;
 * password change and ログアウト on the page.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Core\Domain\People\Gender;
use Modules\Core\Filament\Profile\ProfilePage;

beforeEach(function () {
    $this->user = grantDashboards(User::factory()->create([
        'name' => '山田',
        'password' => 'Old-12345',
        'work_rate' => 1500,
        'sales_staff_code' => 'S1',
    ]), 'erp');
    Filament::setCurrentPanel('erp');
});

it('offers プロフィール, ダッシュボード選択 and ログアウト in the profile menu', function () {
    $this->actingAs($this->user)->get('/erp')
        ->assertOk()
        ->assertSee('プロフィール')
        ->assertSee('ダッシュボード選択')
        ->assertSee('ログアウト')
        ->assertDontSee('パスワード変更');
});

it('shows the user\'s own profile', function () {
    $this->actingAs($this->user)->get('/erp/profile')
        ->assertOk()
        ->assertSee('山田')
        ->assertSee($this->user->email)
        ->assertSee('従業員');
});

it('updates 氏名, フリガナ, 性別 and 住所・連絡先 only', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePage::class)
        ->fillForm([
            'last_name' => '山田',
            'first_name' => '花子',
            'last_name_kana' => 'ヤマダ',
            'first_name_kana' => 'ハナコ',
            'gender' => 'female',
            'postal_code' => '100-0001',
            'address' => '東京都千代田区1-1',
            'mobile_phone' => '090-1111-2222',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('プロフィールを保存しました');

    $user = $this->user->fresh();
    expect($user->first_name)->toBe('花子')
        ->and($user->first_name_kana)->toBe('ハナコ')
        ->and($user->gender)->toBe(Gender::Female)
        ->and($user->address)->toBe('東京都千代田区1-1')
        ->and($user->work_rate)->toBe(1500)
        ->and($user->sales_staff_code)->toBe('S1');
});

it('ignores fields the user may not change, even if sent', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePage::class)
        ->set('data.name', 'Hacker')
        ->set('data.work_rate', 1)
        ->set('data.user_type', 'super_admin')
        ->call('save');

    $user = $this->user->fresh();
    expect($user->name)->toBe('山田')
        ->and($user->work_rate)->toBe(1500)
        ->and($user->isSuperAdmin())->toBeFalse();
});

it('changes the password with the current one', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePage::class)
        ->fillForm(['password' => 'New-56789', 'passwordConfirmation' => 'New-56789', 'currentPassword' => 'wrong'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    Livewire::test(ProfilePage::class)
        ->fillForm(['password' => 'New-56789', 'passwordConfirmation' => 'New-56789', 'currentPassword' => 'Old-12345'])
        ->call('save')
        ->assertHasNoFormErrors();

    $user = $this->user->fresh();
    expect(Hash::check('New-56789', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and($user->must_change_password)->toBeFalse();
});

it('logs out from the profile page', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePage::class)
        ->callAction('logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
