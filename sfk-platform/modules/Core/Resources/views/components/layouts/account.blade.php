@props(['title', 'user' => null])
{{-- legacy-style shell for the portal and account pages. Keep colours in sync with core::filament.theme. --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} | {{ config('core.system_name') }}</title>
    <style>
        :root {
            --kt-blue: #3498db;
            --kt-blue-dark: #1d6fa5;
            --kt-blue-soft: #e2f2fe;
            --kt-normal: #f7f9fa;
            --kt-normal-hover: #c8d6dd;
            --kt-disabled: #d4d7d7;
            --kt-page: #f5f5f5;
            --kt-surface: #ffffff;
            --kt-header: #f7f9fa;
            --kt-border: #e3e7e8;
            --kt-text: #333333;
            --kt-muted: #888888;
            --kt-red: #e74c3c;
            --kt-green: #91c36c;
            --kt-yellow-soft: #fef9e7;
            --kt-yellow-text: #8a6d0b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--kt-page);
            color: var(--kt-text);
            font-family: "メイリオ", Meiryo, "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Noto Sans JP", "Yu Gothic UI", sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        a { color: var(--kt-blue); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Global header (white bar with hairline, like the legacy system) */
        .kt-header {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            height: 48px; padding: 0 20px; background: var(--kt-surface);
            border-bottom: 1px solid var(--kt-border);
        }
        .kt-brand { display: flex; align-items: center; gap: 10px; color: var(--kt-text); font-weight: 700; font-size: 15px; }
        .kt-brand:hover { text-decoration: none; }
        .kt-brand-mark {
            display: grid; place-items: center; width: 28px; height: 28px;
            background: var(--kt-blue); color: #fff; font-size: 11px; letter-spacing: .02em;
        }
        .kt-header-user { display: flex; align-items: center; gap: 18px; font-size: 13px; color: var(--kt-muted); }
        .kt-header-user form { margin: 0; }
        .kt-link-button { border: 0; background: none; padding: 0; font: inherit; color: var(--kt-blue); cursor: pointer; }
        .kt-link-button:hover { text-decoration: underline; }

        main { max-width: 1080px; margin: 0 auto; padding: 28px 20px 56px; }
        h1 { font-size: 20px; line-height: 1.4; margin: 0 0 20px; font-weight: 700; }

        /* Widget box (legacy portal widget / record box) */
        .kt-box { background: var(--kt-surface); border: 1px solid var(--kt-border); border-radius: 0; }
        .kt-box-header {
            display: flex; align-items: center; gap: 8px; padding: 12px 16px;
            background: var(--kt-header); border-bottom: 1px solid var(--kt-border);
            font-weight: 700; font-size: 14px;
        }
        .kt-box-body { padding: 16px; }

        /* Messages: solid bars with white text (success green, danger red), warnings soft yellow */
        .kt-alert { padding: 4px 18px; margin-bottom: 16px; font-size: 14px; line-height: 1.5; border: 1px solid transparent; }
        .kt-alert-success { background: var(--kt-green); color: #fff; }
        .kt-alert-warning { background: var(--kt-yellow-soft); color: var(--kt-yellow-text); border-color: #f7dc6f; }
        .kt-alert-danger { background: var(--kt-red); color: #fff; }

        /* Buttons (legacy vendor UI parts): submit = filled blue, normal = light grey with a blue label */
        .kt-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-width: 163px; max-width: 100%; height: 48px; padding: 0 16px; border-radius: 0;
            border: 1px solid var(--kt-border); background: var(--kt-blue); color: #fff;
            font: inherit; font-size: 16px; cursor: pointer;
        }
        .kt-button:hover { background: var(--kt-blue-dark); text-decoration: none; }
        .kt-button:focus-visible { outline: 1px solid var(--kt-blue); outline-offset: 1px; }
        .kt-button:disabled { background: var(--kt-disabled); color: var(--kt-muted); cursor: not-allowed; }
        .kt-button-normal { background: var(--kt-normal); color: var(--kt-blue); box-shadow: 1px 1px 1px #fff inset; }
        .kt-button-normal:hover { background: var(--kt-normal-hover); }

        /* Text inputs: hairline border with a soft inset shadow, blue border on focus */
        .kt-input {
            width: 100%; height: 40px; padding: 0 8px; border-radius: 0;
            border: 1px solid var(--kt-border); background: #fff; color: #000; font: inherit; font-size: 14px;
            box-shadow: 2px 2px 4px #f5f5f5 inset, -2px -2px 4px #f5f5f5 inset;
        }
        .kt-input:focus { outline: none; border-color: var(--kt-blue); }
        .kt-input[aria-invalid="true"] { border-color: var(--kt-red); }
        .kt-input:disabled { background: var(--kt-disabled); color: var(--kt-muted); box-shadow: none; }
        /* Error under a field: red bar with white text */
        .kt-error { margin: 0; padding: 4px 18px; background: var(--kt-red); color: #fff; font-size: 14px; line-height: 1.5; }

        @media (max-width: 640px) {
            .kt-header { padding: 0 16px; }
            .kt-header-user .kt-user-name { display: none; }
            main { padding: 20px 16px 40px; }
        }
    </style>
</head>
<body>
    <header class="kt-header">
        <a class="kt-brand" href="{{ $user ? route('core.portal') : route('login') }}">
            <span class="kt-brand-mark" aria-hidden="true">SFK</span>
            <span>{{ config('core.system_name') }}</span>
        </a>
        @if ($user)
            <div class="kt-header-user">
                <span class="kt-user-name">{{ $user->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="kt-link-button">ログアウト</button>
                </form>
            </div>
        @endif
    </header>

    <main>
        {{ $slot }}
    </main>
</body>
</html>
