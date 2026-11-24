{{--
    ユーザーの登録 / 編集 (Users\Pages\CreateUser, EditUser — Concerns\HasUserFormPanes), laid out like プロフィール
    (core::filament.profile): the 設定 menu of the form's parts (UserForm::PANES) on the left, the chosen part on the
    right. The menu switches the parts in the browser (UserForm::asPane() shows the part whose key is in `sfkPane`);
    `pane` follows on the next request and keeps the part in the address (?tab=).
--}}
@php
    use Modules\Settings\Filament\Resources\Users\Schemas\UserForm;
@endphp
<x-filament-panels::page>
    <div class="sfk-profile" x-data="{ {{ UserForm::PANE_JS_VARIABLE }}: $wire.entangle('pane') }">
        <nav class="sfk-profile-nav" aria-label="設定">
            <h2 class="sfk-profile-nav-title">設定</h2>
            <ul>
                @foreach (UserForm::PANES as $key => [$label, $icon])
                    <li>
                        <button type="button"
                            x-on:click="{{ UserForm::PANE_JS_VARIABLE }} = @js($key)"
                            x-bind:class="{ 'is-active': {{ UserForm::PANE_JS_VARIABLE }} === @js($key) }"
                            x-bind:aria-current="{{ UserForm::PANE_JS_VARIABLE }} === @js($key) ? 'page' : null"
                            @class(['sfk-profile-nav-item', 'is-active' => $this->pane === $key])
                            @if ($this->pane === $key) aria-current="page" @endif>
                            <x-filament::icon :icon="$icon" class="sfk-profile-nav-icon" />
                            <span>{{ $label }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="sfk-profile-nav-chevron" />
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="sfk-profile-main">
            <h2 class="sfk-profile-title">ユーザー設定</h2>
            {{ $this->content }}
        </div>
    </div>
</x-filament-panels::page>
