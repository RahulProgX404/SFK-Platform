{{--
    ユーザー情報 popup (Modules\Core\Filament\Support\UserPopup): the name opens a card with the user's icon, name,
    従業員カテゴリ, 役職 and プロフィールを表示 (only when the viewer may open it).
--}}
@php
    /** @var \App\Models\User $user */
    $categories = $user->employeeCategories->pluck('name')->implode('・');
    $position = $user->position?->name;
@endphp

<span class="sfk-user" x-on:click.stop>
    <x-filament::dropdown placement="bottom-start" teleport width="xs">
        <x-slot name="trigger">
            <button type="button" class="sfk-user-name" aria-haspopup="dialog">
                <span class="sfk-user-avatar sfk-user-avatar-sm" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>{{ $label }}
            </button>
        </x-slot>

        <div class="sfk-user-card" role="dialog" aria-label="ユーザー情報（{{ $user->name }}）">
            <span class="sfk-user-avatar sfk-user-avatar-lg" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
            <div class="sfk-user-card-body">
                <p class="sfk-user-card-name">{{ $user->name }}</p>
                @if ($categories !== '')
                    <p class="sfk-user-card-meta">{{ $categories }}</p>
                @endif
                @if (filled($position))
                    <p class="sfk-user-card-meta">{{ $position }}</p>
                @endif
                @if ($profileUrl !== null)
                    <a href="{{ $profileUrl }}" class="sfk-user-card-link">プロフィールを表示</a>
                @endif
            </div>
        </div>
    </x-filament::dropdown>
</span>
