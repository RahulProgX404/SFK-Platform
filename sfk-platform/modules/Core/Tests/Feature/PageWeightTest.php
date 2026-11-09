<?php

/*
 * Speed (user, 2026-11-09): pages without the indentation of their HTML (HtmlIndentation, on pages and Livewire
 * components), user names that carry their card instead of a dropdown each (UserPopup, read in two queries for all
 * users), and the 「…」 of list rows as one small button with the page's one menu (AppRecordActions → row-menu).
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Filament\Support\UserPopup;
use Modules\Core\Http\HtmlIndentation;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;

it('strips the indentation of HTML, but not of <pre>, <textarea>, <script> and <style>', function () {
    $html = "<div>\n        <span>a</span>   \n\n    <pre>\n    keep\n</pre>\n  <textarea name=\"n\">\n  line 1\n\n  line 2</textarea>\n"
        ."   <script>\n    if (a) {\n        b()\n    }\n</script>\n  <style>\n  .x { color: red; }\n</style>\n    <p x-data=\"{\n        // a comment\n        open: false\n    }\">t</p>\n</div>";

    $stripped = HtmlIndentation::strip($html);

    expect($stripped)->toContain("<div>\n<span>a</span>\n\n<pre>\n    keep\n</pre>")
        ->toContain("<textarea name=\"n\">\n  line 1\n\n  line 2</textarea>")
        ->toContain("<script>\n    if (a) {\n        b()\n    }\n</script>")
        ->toContain("<style>\n  .x { color: red; }\n</style>")
        // a line comment in an attribute stays on its own line
        ->toContain("x-data=\"{\n// a comment\nopen: false\n}\"")
        ->and(preg_replace('/\s+/', '', $stripped))->toBe(preg_replace('/\s+/', '', $html))
        ->and(HtmlIndentation::strip($stripped))->toBe($stripped);
});

it('serves pages without their indentation', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $page = (string) $this->get('/erp')->assertOk()->getContent();

    expect($page)->not->toMatch('/\n[ \t]{8,}<div/');
});

it('gives each user name its card, reading 従業員カテゴリ and 役職 once for all users', function () {
    $viewer = User::factory()->superAdmin()->create();
    $this->actingAs($viewer);
    $category = EmployeeCategory::query()->firstOrCreate(['name' => '営業'], ['sort_order' => 1]);
    $position = Position::query()->firstOrCreate(['name' => '課長']);
    $users = User::factory()->count(5)->create(['position_id' => $position->id]);
    $users->each(fn (User $user) => $user->employeeCategories()->attach($category));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $html = UserPopup::list($users)->toHtml();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $first = $users->first();
    expect($queries)->toBeLessThanOrEqual(3)
        ->and($html)->toContain('data-sfk-user="'.e(json_encode([
            'n' => $first->name, 'm' => ['営業', '課長'], 'u' => route(UserPopup::SETTINGS_USER_ROUTE, ['record' => $first]),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"')
        ->and(substr_count($html, 'data-sfk-user='))->toBe(5)
        ->and($html)->not->toContain('fi-dropdown');
});
