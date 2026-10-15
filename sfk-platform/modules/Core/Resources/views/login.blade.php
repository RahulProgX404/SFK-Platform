<x-core::layouts.account title="ログイン">
    <style>
        .kt-login { max-width: 420px; margin: 40px auto 0; }
        .kt-login h1 { text-align: center; font-size: 22px; margin-bottom: 24px; }
        .kt-field { margin-bottom: 18px; }
        .kt-field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; }
        .kt-field input[type="email"], .kt-field input[type="password"] {
            width: 100%; height: 40px; padding: 0 10px; border-radius: 0;
            border: 1px solid var(--kt-border); background: #fff; color: var(--kt-text); font: inherit;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, .06);
        }
        .kt-field input:focus { outline: none; border-color: var(--kt-blue); box-shadow: 0 0 0 2px rgba(52, 152, 219, .25); }
        .kt-field input[aria-invalid="true"] { border-color: var(--kt-red); }
        .kt-error { margin: 6px 0 0; font-size: 12px; color: var(--kt-red); }
        .kt-remember { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 20px; }
        .kt-login .kt-button { width: 100%; }
    </style>

    <div class="kt-login">
        <h1>{{ config('core.system_name') }}</h1>

        <section class="kt-box">
            <h2 class="kt-box-header" style="margin: 0">ログイン</h2>
            <div class="kt-box-body">
                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf

                    <div class="kt-field">
                        <label for="email">メールアドレス</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <p class="kt-error" id="email-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kt-field">
                        <label for="password">パスワード</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p class="kt-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="kt-remember">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        ログインしたままにする
                    </label>

                    <button type="submit" class="kt-button">ログイン</button>
                </form>
            </div>
        </section>
    </div>
</x-core::layouts.account>
