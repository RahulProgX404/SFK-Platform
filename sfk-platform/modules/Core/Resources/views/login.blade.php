<x-core::layouts.account title="ログイン">
    <style>
        .kt-login { max-width: 420px; margin: 40px auto 0; }
        .kt-login h1 { text-align: center; font-size: 25px; margin-bottom: 24px; }
        .kt-field { margin-bottom: 18px; }
        .kt-field label { display: block; padding: 4px 0 8px; font-size: 16px; }
        .kt-remember { display: flex; align-items: center; gap: 8px; font-size: 15px; margin-bottom: 20px; }
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
                        <input class="kt-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <p class="kt-error" id="email-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kt-field">
                        <label for="password">パスワード</label>
                        <input class="kt-input" id="password" name="password" type="password" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p class="kt-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="kt-remember">
                        <input type="checkbox" class="sfk-ui-check" name="remember" value="1" @checked(old('remember'))>
                        ログインしたままにする
                    </label>

                    <button type="submit" class="kt-button">ログイン</button>
                </form>
            </div>
        </section>
    </div>
</x-core::layouts.account>
