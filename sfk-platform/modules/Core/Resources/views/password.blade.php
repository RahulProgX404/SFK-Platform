@php
    /** @var \App\Models\User $user */
@endphp

<x-core::layouts.account title="パスワード変更" :user="$user" :logout-url="$logoutUrl">
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
        .kt-hint { margin: 6px 0 0; font-size: 12px; color: var(--kt-muted); }
        .kt-error { margin: 6px 0 0; font-size: 12px; color: var(--kt-red); }
        .kt-actions {
            display: flex; flex-wrap: wrap; gap: 12px; justify-content: center;
            margin: 8px -16px -16px; padding: 16px; border-top: 1px solid var(--kt-border); background: var(--kt-header);
        }
        .kt-lead { margin: 0 0 16px; color: var(--kt-muted); font-size: 13px; }
    </style>

    <div class="kt-password">
        <h1>パスワードの変更</h1>

        <section class="kt-box">
            <h2 class="kt-box-header" style="margin: 0">{{ $user->name }} さんのパスワード</h2>
            <div class="kt-box-body">
                @if ($forced)
                    <div class="kt-alert kt-alert-warning" role="alert">
                        管理者が設定した仮パスワードでログインしています。<br>
                        続行するには、新しいパスワードを設定してください。
                    </div>
                @else
                    <p class="kt-lead">現在のパスワードと新しいパスワードを入力してください。</p>
                @endif

                @if ($user->managed_by_env)
                    <div class="kt-alert kt-alert-danger" role="alert">
                        このアカウント（スーパー管理者）のパスワードは環境変数で管理されているため、画面からは変更できません。
                    </div>
                    <div class="kt-actions">
                        <a class="kt-button kt-button-normal" href="{{ route('core.portal') }}">ダッシュボード選択に戻る</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('core.password.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="kt-field">
                            <label for="current_password">{{ $forced ? '仮パスワード' : '現在のパスワード' }}<span class="kt-required" aria-hidden="true">*</span></label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus
                                   @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                            @error('current_password')
                                <p class="kt-error" id="current_password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kt-field">
                            <label for="password">新しいパスワード<span class="kt-required" aria-hidden="true">*</span></label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required
                                   aria-describedby="password-hint @error('password') password-error @enderror"
                                   @error('password') aria-invalid="true" @enderror>
                            <p class="kt-hint" id="password-hint">8文字以上で、英字と数字を両方含めてください。</p>
                            @error('password')
                                <p class="kt-error" id="password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kt-field">
                            <label for="password_confirmation">新しいパスワード（確認）<span class="kt-required" aria-hidden="true">*</span></label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>

                        <div class="kt-actions">
                            @unless ($forced)
                                <a class="kt-button kt-button-normal" href="{{ route('core.portal') }}">キャンセル</a>
                            @endunless
                            <button type="submit" class="kt-button">変更を保存</button>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    </div>
</x-core::layouts.account>
