@php
    /** @var \App\Models\User $user */
@endphp

<x-core::layouts.account title="パスワード変更" :user="$user">
    <style>
        .kt-password { max-width: 520px; margin: 0 auto; }
        .kt-field { margin-bottom: 18px; }
        .kt-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; }
        .kt-required { color: var(--kt-red); margin-left: 2px; }
        .kt-field input {
            width: 100%; height: 40px; padding: 0 10px; border-radius: 0;
            border: 1px solid var(--kt-border); background: #fff; color: var(--kt-text); font: inherit;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, .06);
        }
        .kt-field input:focus { outline: none; border-color: var(--kt-blue); box-shadow: 0 0 0 2px rgba(52, 152, 219, .25); }
        .kt-field input[aria-invalid="true"] { border-color: var(--kt-red); }
        .kt-error { margin: 6px 0 0; font-size: 12px; color: var(--kt-red); }
        .kt-actions {
            display: flex; flex-wrap: wrap; gap: 12px; justify-content: center;
            margin: 8px -16px -16px; padding: 16px; border-top: 1px solid var(--kt-border); background: var(--kt-header);
        }
        .kt-actions form { margin: 0; }
    </style>

    <div class="kt-password">
        <h1>パスワードの変更</h1>

        <section class="kt-box">
            <h2 class="kt-box-header" style="margin: 0">{{ $user->name }} さんのパスワード</h2>
            <div class="kt-box-body">
                <form id="change-password" method="POST" action="{{ route('core.password.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="kt-field">
                        <label for="password">新しいパスワード<span class="kt-required" aria-hidden="true">*</span></label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p class="kt-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kt-field">
                        <label for="password_confirmation">新しいパスワード（確認）<span class="kt-required" aria-hidden="true">*</span></label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </form>

                <div class="kt-actions">
                    <form method="POST" action="{{ route('core.password.keep') }}">
                        @csrf
                        <button type="submit" class="kt-button kt-button-normal">仮パスワードをそのまま使う</button>
                    </form>
                    <button type="submit" form="change-password" class="kt-button">変更を保存</button>
                </div>
            </div>
        </section>
    </div>
</x-core::layouts.account>
