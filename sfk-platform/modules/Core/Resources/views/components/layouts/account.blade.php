@props(['title', 'user' => null])
{{-- Shell of the portal and the account pages (login, password). Keep colours in sync with core::filament.design. --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} | {{ config('core.system_name') }}</title>
    <link rel="stylesheet" href="{{ \App\Modules\Filament\BasePanelProvider::FONT_STYLESHEET }}">
    <style>
        :root {
            --sfk-radius: 6px;
            --sfk-radius-lg: 10px;
            --sfk-ink: #111827;
            --sfk-line: #e5e7eb;
            --sfk-shadow: 0 1px 2px rgba(16, 24, 40, .05);
            --kt-blue: #05467E;
            --kt-blue-dark: #033456;
            --kt-blue-soft: #e6eef5;
            --kt-normal: #ffffff;
            --kt-normal-hover: #f3f4f6;
            --kt-disabled: #eef0f3;
            --kt-page: #f6f7f9;
            --kt-surface: #ffffff;
            --kt-header: #f9fafb;
            --kt-border: #e5e7eb;
            --kt-text: #1f2937;
            --kt-muted: #6b7280;
            --kt-red: #e74c3c;
            --kt-green: #91c36c;
            --kt-yellow-soft: #fef9e7;
            --kt-yellow-text: #8a6d0b;
        }
        * { box-sizing: border-box; }
        body {
            display: flex;
            flex-direction: column;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #f7fbff 0%, #eef4fa 100%);
            color: var(--kt-text);
            font-family: "Noto Sans JP", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic UI", Meiryo, system-ui, sans-serif;
            letter-spacing: .01em;
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.6;
        }
        a { color: var(--kt-blue); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Global header (white bar with hairline, like the legacy system) */
        .kt-header {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            height: 52px; padding: 0 24px; background: var(--kt-surface);
            box-shadow: 0 1px 0 var(--kt-border), 0 4px 14px rgba(16, 24, 40, .04);
        }
        .kt-brand { display: flex; align-items: center; gap: 12px; color: var(--sfk-ink); font-weight: 700; font-size: 17px; }
        .kt-brand:hover { text-decoration: none; }
        .kt-brand-mark {
            display: grid; place-items: center; width: 32px; height: 32px; border-radius: 8px;
            background: linear-gradient(135deg, #2b6aa0, var(--kt-blue-dark)); color: #fff; font-size: 11px; letter-spacing: .02em;
        }
        .kt-header-user { display: flex; align-items: center; gap: 14px; font-size: 13px; color: var(--kt-text); }
        .kt-header-user svg { width: 18px; height: 18px; }
        .kt-user-name { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
        .kt-user-name svg { color: #64748b; }
        .kt-header-sep { width: 1px; height: 16px; background: var(--kt-border); }
        .kt-header-user form { margin: 0; }
        .kt-link-button { display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; padding: 0; font: inherit; font-weight: 600; color: var(--kt-blue); cursor: pointer; }
        .kt-link-button:hover { text-decoration: underline; }

        main { flex: 1; width: 100%; max-width: 1080px; margin: 0 auto; padding: 24px 20px 40px; }
        .kt-footer { padding: 12px 20px; border-top: 1px solid #dde7f1; background: linear-gradient(180deg, rgba(255, 255, 255, 0), #e3eef9); text-align: center; font-size: 12px; color: var(--kt-muted); }
        h1 { font-size: 18px; line-height: 1.4; margin: 0 0 16px; font-weight: 700; }

        /* Widget box (legacy portal widget / record box) */
        .kt-box { background: var(--kt-surface); border: 1px solid var(--kt-border); border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-shadow); overflow: hidden; }
        .kt-box-header {
            display: flex; align-items: center; gap: 8px; padding: 12px 16px;
            background: var(--kt-header); border-bottom: 1px solid var(--kt-border);
            font-weight: 600; font-size: 14px;
        }
        .kt-box-body { padding: 16px; }

        /* Messages: solid bars with white text (success green, danger red), warnings soft yellow */
        .kt-alert { padding: 8px 16px; margin-bottom: 16px; font-size: 14px; line-height: 1.5; border: 1px solid transparent; border-radius: var(--sfk-radius); }
        .kt-alert-success { background: var(--kt-green); color: #fff; }
        .kt-alert-warning { background: var(--kt-yellow-soft); color: var(--kt-yellow-text); border-color: #f7dc6f; }
        .kt-alert-danger { background: var(--kt-red); color: #fff; }

        /* Buttons (legacy vendor UI parts): submit = filled blue, normal = light grey with a blue label */
        .kt-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-width: 140px; max-width: 100%; height: 38px; padding: 0 18px; border-radius: var(--sfk-radius);
            border: 1px solid transparent; background: var(--kt-blue); color: #fff; box-shadow: var(--sfk-shadow);
            font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .kt-button:hover { background: var(--kt-blue-dark); text-decoration: none; }
        .kt-button:focus-visible { outline: 1px solid var(--kt-blue); outline-offset: 1px; }
        .kt-button:disabled { background: var(--kt-disabled); color: var(--kt-muted); cursor: not-allowed; }
        .kt-button-normal { background: #fff; color: var(--kt-text); border-color: var(--kt-border); }
        .kt-button-normal:hover { background: var(--kt-normal-hover); }

        /* Text inputs: hairline border with a soft inset shadow, blue border on focus */
        .kt-input {
            width: 100%; height: 38px; padding: 0 12px; border-radius: var(--sfk-radius);
            border: 1px solid #dfe3e8; background: #fff; color: var(--sfk-ink); font: inherit; font-size: 14px;
        }
        .kt-input:focus { outline: none; border-color: var(--kt-blue); box-shadow: 0 0 0 4px #e8eff6; }
        .kt-input[aria-invalid="true"] { border-color: var(--kt-red); }
        .kt-input:disabled { background: var(--kt-disabled); color: var(--kt-muted); box-shadow: none; }
        /* Error under a field: red bar with white text */
        .kt-error { margin: 4px 0 0; padding: 0; color: var(--kt-red); font-size: 13px; line-height: 1.5; }

        @media (max-width: 640px) {
            .kt-header { padding: 0 16px; }
            .kt-header-user .kt-user-name { display: none; }
            main { padding: 20px 16px 40px; }
        }
    </style>
    {{-- SFK UI component layer (check box of ログインしたままにする) --}}
    @include('core::filament.ui')
</head>
<body>
    <header class="kt-header">
        <a class="kt-brand" href="{{ $user ? route('core.portal') : route('login') }}">
            <span class="kt-brand-mark" aria-hidden="true">SFK</span>
            <span>{{ config('core.system_name') }}</span>
        </a>
        @if ($user)
            <div class="kt-header-user">
                <span class="kt-user-name">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" /></svg>
                    {{ $user->name }}
                </span>
                <span class="kt-header-sep" aria-hidden="true"></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="kt-link-button">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M19 10a.75.75 0 0 0-.75-.75H8.704l1.048-.943a.75.75 0 1 0-1.004-1.114l-2.5 2.25a.75.75 0 0 0 0 1.114l2.5 2.25a.75.75 0 1 0 1.004-1.114l-1.048-.943h9.546A.75.75 0 0 0 19 10Z" clip-rule="evenodd" /></svg>
                        ログアウト
                    </button>
                </form>
            </div>
        @endif
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="kt-footer">© {{ config('core.system_name') }}</footer>
</body>
</html>
