<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Application\Cache\ReferenceCache;
use WeakMap;

/**
 * A user's name that opens the ユーザー情報 popup (legacy user information pop-up): icon, name, 従業員カテゴリ, 役職 and
 * the link to the profile when the viewer may open it (their own プロフィール, or the user's page in 設定 for those
 * who may open that dashboard). Use html() in table columns and record entries (->html()) and in Blade views.
 *
 * Light on purpose (2026-11-09, speed): a list shows hundreds of names, so each is one small button carrying its card
 * (`data-sfk-user`), and one script of the theme (core::filament.user-popup-script) draws the card when a name is
 * clicked — no dropdown component per name. The 従業員カテゴリ and 役職 of all users are read once per request (two
 * queries) instead of once per name.
 */
final class UserPopup
{
    public const SETTINGS_PANEL = 'settings';

    public const SETTINGS_USER_ROUTE = 'filament.settings.resources.users.view';

    /** @var WeakMap<Request, array{cards: array<int, list<string>>, settings: bool|null}>|null per request */
    private static ?WeakMap $perRequest = null;

    /** @param  string|null  $label  shown instead of the name (e.g. 「【0101】山田太郎」) */
    public static function html(?User $user, ?string $label = null): HtmlString
    {
        if ($user === null) {
            return new HtmlString('');
        }

        $card = ['n' => $user->name, 'm' => self::meta($user)];
        $url = self::profileUrl($user);
        if ($url !== null) {
            $card['u'] = $url;
        }

        return new HtmlString('<span class="sfk-user"><button type="button" class="sfk-user-name" aria-haspopup="dialog" aria-expanded="false" data-sfk-user="'
            .e((string) json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"><span class="sfk-user-avatar sfk-user-avatar-sm" aria-hidden="true">'
            .e(mb_substr($user->name, 0, 1)).'</span>'.e($label ?? $user->name).'</button></span>');
    }

    /**
     * Several users, one per line.
     *
     * @param  iterable<User>  $users
     * @param  (callable(User): string)|null  $label
     */
    public static function list(iterable $users, ?callable $label = null): HtmlString
    {
        $lines = [];
        foreach ($users as $user) {
            $lines[] = '<span class="sfk-user-line">'.self::html($user, $label !== null ? $label($user) : null)->toHtml().'</span>';
        }

        return new HtmlString(implode('', $lines));
    }

    public static function profileUrl(User $user): ?string
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            return null;
        }

        if ($viewer->is($user)) {
            return Filament::getCurrentPanel()?->getProfileUrl();
        }

        $memo = self::memo();
        if ($memo['settings'] === null) {
            $memo['settings'] = Route::has(self::SETTINGS_USER_ROUTE) && DashboardAccess::allows($viewer, self::SETTINGS_PANEL);
            self::remember($memo);
        }

        return $memo['settings'] ? route(self::SETTINGS_USER_ROUTE, ['record' => $user]) : null;
    }

    /**
     * The lines under the name on the card: 従業員カテゴリ (joined with ・) and 役職, the empty ones left out.
     *
     * @return list<string>
     */
    public static function meta(User $user): array
    {
        $memo = self::memo();
        if ($memo['cards'] === []) {
            $memo['cards'] = self::cards();
            self::remember($memo);
        }

        // a user created after the cards were read (in this request)
        if (! array_key_exists($user->id, $memo['cards'])) {
            $memo['cards'][$user->id] = array_values(array_filter([
                $user->employeeCategories->pluck('name')->implode('・'),
                (string) $user->position?->name,
            ], fn (string $line): bool => $line !== ''));
            self::remember($memo);
        }

        return $memo['cards'][$user->id];
    }

    /** Drops what was read for this request (e.g. after a user's 従業員カテゴリ changed). */
    public static function forget(): void
    {
        self::$perRequest = null;
    }

    /**
     * The card lines of every user: two queries for all of them, kept in the cache between requests (ReferenceCache —
     * users, 従業員カテゴリ and 役職 bump it when they change).
     *
     * @return array<int, list<string>>
     */
    private static function cards(): array
    {
        return ReferenceCache::remember('user-cards', self::readCards(...));
    }

    /** @return array<int, list<string>> */
    private static function readCards(): array
    {
        $categories = [];
        foreach (DB::table('employee_category_user')
            ->join('employee_categories', 'employee_categories.id', '=', 'employee_category_user.employee_category_id')
            ->orderBy('employee_categories.sort_order')
            ->orderBy('employee_categories.id')
            ->get(['employee_category_user.user_id', 'employee_categories.name']) as $row) {
            $categories[(int) $row->user_id][] = (string) $row->name;
        }

        $cards = [];
        foreach (DB::table('users')->leftJoin('positions', 'positions.id', '=', 'users.position_id')->get(['users.id', 'positions.name']) as $row) {
            $id = (int) $row->id;
            $cards[$id] = array_values(array_filter([implode('・', $categories[$id] ?? []), (string) $row->name], fn (string $line): bool => $line !== ''));
        }

        return $cards;
    }

    /** @return array{cards: array<int, list<string>>, settings: bool|null} */
    private static function memo(): array
    {
        self::$perRequest ??= new WeakMap;

        return self::$perRequest[request()] ?? ['cards' => [], 'settings' => null];
    }

    /** @param  array{cards: array<int, list<string>>, settings: bool|null}  $memo */
    private static function remember(array $memo): void
    {
        self::$perRequest ??= new WeakMap;
        self::$perRequest[request()] = $memo;
    }
}
