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
            --kt-blue-dark: #217dbb;
            --kt-button: #1f78b8;
            --kt-button-hover: #1a6699;
            --kt-blue-soft: #ebf5fb;
            --kt-page: #f5f5f5;
            --kt-surface: #ffffff;
            --kt-header: #f7f9fa;
            --kt-border: #e3e7e8;
            --kt-text: #333333;
            --kt-muted: #888888;
            --kt-red: #e74c3c;
            --kt-red-soft: #fdedec;
            --kt-green: #1e8449;
            --kt-green-soft: #eafaf1;
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
            display: grid; place-items: center; width: 28px; height: 28px; border-radius: 3px;
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

        /* Messages */
        .kt-alert { padding: 10px 14px; margin-bottom: 16px; font-size: 13px; border: 1px solid transparent; border-radius: 2px; }
        .kt-alert-success { background: var(--kt-green-soft); color: var(--kt-green); border-color: #abebc6; }
        .kt-alert-warning { background: var(--kt-yellow-soft); color: var(--kt-yellow-text); border-color: #f7dc6f; }
        .kt-alert-danger { background: var(--kt-red-soft); color: var(--kt-red); border-color: #f5b7b1; }

        /* Buttons (the legacy system: filled blue for the main action, white with border for the rest) */
        .kt-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-width: 140px; height: 44px; padding: 0 20px; border-radius: 2px;
            border: 1px solid var(--kt-button); background: var(--kt-button); color: #fff;
            font: inherit; font-weight: 700; cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .1);
        }
        .kt-button:hover { background: var(--kt-button-hover); border-color: var(--kt-button-hover); text-decoration: none; }
        .kt-button-normal { background: #fff; color: var(--kt-button); border-color: var(--kt-border); }
        .kt-button-normal:hover { background: var(--kt-header); border-color: var(--kt-border); }

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
