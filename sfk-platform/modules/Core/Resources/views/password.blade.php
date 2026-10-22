@php
    /** @var \App\Models\User $user */
@endphp

<x-core::layouts.account title="パスワード変更" :user="$user">
    <style>
        .kt-password { max-width: 520px; margin: 0 auto; }
        .kt-field { margin-bottom: 18px; }
        .kt-field label { display: block; padding: 4px 0 8px; font-size: 16px; }
        .kt-required { color: var(--kt-red); margin-left: 2px; font-size: 23px; line-height: 1; }
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
                        <input class="kt-input" id="password" name="password" type="password" autocomplete="new-password" required autofocus
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p class="kt-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kt-field">
                        <label for="password_confirmation">新しいパスワード（確認）<span class="kt-required" aria-hidden="true">*</span></label>
                        <input class="kt-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
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
