<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Users;

use App\Models\User;
use Closure;
use Filament\Widgets\Widget;

/**
 * Cards other modules show on the page of a user (ユーザー管理 → a user, 02. 従業員マスタ → a record), above it — e.g.
 * 個人の概要 of the Analytics module (bug.md 2026-11-06) — where the pages' modules cannot depend on them. A module adds
 * a widget (it gets the user as `record`) with the rule of who may see it for whom; the pages ask for those that apply.
 */
final class UserDetailWidgets
{
    /** @var list<array{widget: class-string<Widget>, when: Closure(User, User): bool}> */
    private array $widgets = [];

    /**
     * @param  class-string<Widget>  $widget
     * @param  Closure(User $viewer, User $person): bool  $when
     */
    public function add(string $widget, Closure $when): void
    {
        $this->widgets[] = ['widget' => $widget, 'when' => $when];
    }

    /** @return list<class-string<Widget>> the cards the signed-in user may see on the page of $person */
    public function for(User $person): array
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            return [];
        }

        return array_values(array_map(
            fn (array $entry): string => $entry['widget'],
            array_filter($this->widgets, fn (array $entry): bool => ($entry['when'])($viewer, $person)),
        ));
    }
}
