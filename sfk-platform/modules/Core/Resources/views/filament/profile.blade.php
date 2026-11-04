{{--
    プロフィール (Modules\Core\Filament\Profile\ProfilePage, design_ref/profile-page.png): the menu of settings on the
    left (only those the user may open), the chosen one on the right.
--}}
<x-filament-panels::page>
    <div class="sfk-profile">
        <nav class="sfk-profile-nav" aria-label="設定">
            <h2 class="sfk-profile-nav-title">設定</h2>
            <ul>
                @foreach ($this->sections() as $key => [$label, $icon])
                    <li>
                        <button type="button" wire:click="showSection(@js($key))"
                            @class(['sfk-profile-nav-item', 'is-active' => $this->section === $key])
                            @if ($this->section === $key) aria-current="page" @endif>
                            <x-filament::icon :icon="$icon" class="sfk-profile-nav-icon" />
                            <span>{{ $label }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="sfk-profile-nav-chevron" />
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="sfk-profile-main">
            <h2 class="sfk-profile-title">アカウント設定</h2>
            {{ $this->content }}
        </div>
    </div>
</x-filament-panels::page>
