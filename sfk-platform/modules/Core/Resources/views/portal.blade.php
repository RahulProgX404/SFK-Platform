@php
    /** @var \App\Models\User $user */
    /** @var list<\Modules\Core\Http\Portal\DashboardEntry> $dashboards */
    /** @var list<array{dashboard: string, html: string|null, edit_url: string|null}> $notices */
    $icons = [
        'erp' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
        'default' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
    ];
    // Flat app-icon colours, as on the legacy system's app tiles.
    $iconColors = ['erp' => '#05467E', 'settings' => '#64748b'];

    $now = now();
    $hour = (int) $now->format('G');
    $greeting = match (true) {
        $hour >= 4 && $hour < 11 => 'おはようございます',
        $hour >= 11 && $hour < 18 => 'こんにちは',
        default => 'こんばんは',
    };
@endphp

<x-core::layouts.account title="ダッシュボード選択" :user="$user">
    <style>
        main { max-width: 1160px; }

        /* welcome card (design_ref/portal.png): a soft blue ground, a blue mark before the greeting, the date and a clock */
        .sp-welcome { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 196px; margin: 8px 0 40px; padding: 34px 44px; overflow: hidden; border-radius: 16px; background: linear-gradient(115deg, #ffffff 0%, #eef6fe 45%, #d9ebfb 100%); box-shadow: 0 0 0 1px #e1ecf7, 0 10px 30px rgba(5, 70, 126, .12); }
        .sp-welcome::before { content: ""; position: absolute; right: 18%; bottom: -120px; width: 420px; height: 260px; border-radius: 50%; background: rgba(255, 255, 255, .55); transform: rotate(-18deg); }
        .sp-welcome-text { position: relative; min-width: 0; }
        .sp-welcome-greeting { padding-left: 22px; border-left: 6px solid var(--kt-blue); }
        .sp-welcome h1 { margin: 0; font-size: 30px; font-weight: 700; line-height: 1.35; color: var(--sfk-ink); }
        .sp-welcome-note { margin: 8px 0 0; font-size: 17px; font-weight: 600; color: #4b5563; }
        .sp-welcome-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 22px; font-size: 16px; color: var(--sfk-text); font-variant-numeric: tabular-nums; }
        .sp-welcome-meta span { display: inline-flex; align-items: center; gap: 8px; }
        .sp-welcome-meta svg { width: 20px; height: 20px; color: var(--kt-blue); }
        .sp-welcome-meta { gap: 28px; padding-left: 28px; }
        .sp-welcome-art { position: relative; flex: none; width: 250px; height: 150px; }
        @media (max-width: 760px) { .sp-welcome { padding: 24px; } .sp-welcome h1 { font-size: 24px; } .sp-welcome-art { display: none; } }

        .sp-heading { display: flex; align-items: center; gap: 14px; margin: 0 0 18px; }
        .sp-heading h2 { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 22px; font-weight: 700; color: var(--sfk-ink); white-space: nowrap; }
        .sp-heading-icon { width: 30px; height: 30px; color: var(--kt-blue); }
        .sp-heading-line { flex: 1; height: 1px; background: #d5e1ec; }

        .sp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin: 0; padding: 0; list-style: none; }
        .sp-card { position: relative; display: flex; align-items: center; gap: 22px; height: 100%; padding: 24px 28px; overflow: hidden; border-radius: 14px; background: linear-gradient(135deg, #ffffff 55%, #f3f8fd 100%); box-shadow: 0 0 0 1px #e3ebf3, 0 6px 18px rgba(16, 24, 40, .06); color: var(--kt-text); }
        .sp-card:hover { text-decoration: none; box-shadow: 0 0 0 1px var(--kt-blue), 0 10px 24px rgba(5, 70, 126, .16); }
        .sp-card:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 2px; }
        .sp-icon { display: grid; place-items: center; width: 68px; height: 68px; flex-shrink: 0; border-radius: 16px; color: #fff; box-shadow: 0 6px 14px rgba(16, 24, 40, .12); }
        .sp-icon svg { width: 36px; height: 36px; }
        .sp-name { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; }
        .sp-name strong { font-size: 22px; font-weight: 700; color: var(--sfk-ink); }
        .sp-name span { font-size: 15px; color: var(--kt-muted); }
        .sp-arrow { display: grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 9999px; background: #e3f0fb; color: var(--kt-blue); }
        .sp-card:hover .sp-arrow { background: var(--kt-blue); color: #fff; }
        .sp-arrow svg { width: 20px; height: 20px; }
        .sp-empty { padding: 48px 16px; border: 1px solid var(--kt-border); border-radius: 14px; background: #fff; text-align: center; color: var(--kt-muted); }

        /* お知らせ of the dashboards */
        .sp-notices { display: flex; flex-direction: column; gap: 20px; margin-top: 32px; }
        .sp-notice { overflow: hidden; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px #e3ebf3, 0 6px 18px rgba(16, 24, 40, .05); }
        .sp-notice-head { display: flex; align-items: center; gap: 14px; padding: 18px 28px; border-bottom: 1px solid var(--kt-border); }
        .sp-notice-icon { width: 34px; height: 34px; flex: none; color: var(--kt-blue); }
        .sp-notice-title { display: flex; flex: 1; align-items: center; gap: 14px; margin: 0; font-size: 24px; font-weight: 700; color: var(--sfk-ink); }
        .sp-notice-dashboard { padding: 3px 14px; border-radius: 9999px; background: #e3f0fb; color: var(--kt-blue-dark); font-size: 15px; font-weight: 600; }
        .sp-notice-edit { display: grid; place-items: center; width: 42px; height: 42px; border: 1px solid #d5dde6; border-radius: 8px; background: #fff; color: var(--kt-text); }
        .sp-notice-edit:hover { border-color: var(--kt-blue); color: var(--kt-blue-dark); text-decoration: none; }
        .sp-notice-edit svg { width: 22px; height: 22px; }
        .sp-notice-body { padding: 22px 28px 26px; }
        .sp-notice-none { margin: 0; font-size: 15px; color: var(--kt-muted); }
        .sp-notice-content { font-size: 17px; line-height: 1.9; overflow-x: auto; }
        .sp-notice-content h2 { font-size: 26px; font-weight: 700; margin: 4px 0 12px; }
        .sp-notice-content h3 { font-size: 21px; font-weight: 700; margin: 12px 0 8px; }
        .sp-notice-content p { margin: 0; }
        .sp-notice-content ul { list-style: disc; padding-left: 24px; }
        .sp-notice-content ol { list-style: decimal; padding-left: 24px; }
        .sp-notice-content a { color: var(--kt-blue); text-decoration: underline; }
        /* the highlights of the editor as soft rounded chips */
        .sp-notice-content mark { padding: 1px 8px; border-radius: 6px; }
        /* 文字色 of the editor: <span class="color" style="--color: …"> */
        .sp-notice-content .color { color: var(--color); }
        .sp-notice-content table { border-collapse: collapse; margin: 8px 0; }
        .sp-notice-content td, .sp-notice-content th { border: 1px solid var(--kt-border); padding: 4px 8px; }
    </style>

    @if (session('status'))
        <div class="kt-alert kt-alert-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="sp-welcome" aria-label="ようこそ">
        <div class="sp-welcome-text">
            <div class="sp-welcome-greeting">
                <h1>{{ $greeting }}、{{ $user->name }} さん</h1>
                <p class="sp-welcome-note">{{ $unread > 0 ? '未読の通知が '.number_format($unread).' 件あります' : '未読の通知はありません' }}</p>
            </div>
            <div class="sp-welcome-meta">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
                    {{ $now->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
                </span>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                    {{-- a clock that keeps going --}}
                    <time id="sp-clock">{{ $now->format('H:i') }}</time>
                </span>
            </div>
        </div>

        {{-- a monitor with a list and a check mark --}}
        <svg class="sp-welcome-art" viewBox="0 0 250 150" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="70" cy="112" r="34" fill="#cfe0f0" fill-opacity=".6" />
            <rect x="62" y="10" width="150" height="104" rx="8" fill="#0f2238" />
            <rect x="70" y="18" width="134" height="88" rx="4" fill="#f4f8fc" />
            <rect x="84" y="34" width="70" height="8" rx="4" fill="#9db7d0" />
            <rect x="84" y="50" width="96" height="6" rx="3" fill="#c6d6e6" />
            <rect x="84" y="62" width="84" height="6" rx="3" fill="#c6d6e6" />
            <rect x="84" y="74" width="92" height="6" rx="3" fill="#c6d6e6" />
            <path d="M126 114h22l4 18h-30z" fill="#d6e1ec" />
            <rect x="108" y="130" width="58" height="6" rx="3" fill="#c3d2e0" />
            <circle cx="200" cy="96" r="22" fill="#05467E" />
            <path d="M190 96l7 7 13-14" stroke="#ffffff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </section>

    <section aria-labelledby="dashboards-heading">
        <div class="sp-heading">
            <h2 id="dashboards-heading">
                <svg class="sp-heading-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2" /><rect x="13" y="3" width="8" height="8" rx="2" /><rect x="3" y="13" width="8" height="8" rx="2" /><rect x="13" y="13" width="8" height="8" rx="2" /></svg>
                ダッシュボード
            </h2>
            <span class="sp-heading-line" aria-hidden="true"></span>
        </div>

        @if ($dashboards === [])
            <p class="sp-empty">利用できるダッシュボードがありません。</p>
        @else
            <ul class="sp-grid">
                @foreach ($dashboards as $dashboard)
                    @php($color = $iconColors[$dashboard->id] ?? '#05467E')
                    <li>
                        <a href="{{ $dashboard->url }}" class="sp-card">
                            <span class="sp-icon" style="background: {{ $color }}" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">{!! $icons[$dashboard->icon] ?? $icons['default'] !!}</svg>
                            </span>
                            <span class="sp-name">
                                <strong>{{ $dashboard->label }}</strong>
                                @if (filled($dashboard->subtitle))
                                    <span>{{ $dashboard->subtitle }}</span>
                                @endif
                            </span>
                            <span class="sp-arrow" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($notices !== [])
        <div class="sp-notices">
            @foreach ($notices as $notice)
                <section class="sp-notice" aria-label="お知らせ（{{ $notice['dashboard'] }}）">
                    <header class="sp-notice-head">
                        <svg class="sp-notice-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.881 4.345A23.112 23.112 0 0 1 8.25 6H7.5a5.25 5.25 0 0 0-.88 10.427 21.593 21.593 0 0 0 1.378 3.94c.464 1.004 1.674 1.32 2.582.796l.657-.379c.88-.508 1.165-1.593.772-2.468a17.116 17.116 0 0 1-.628-1.607c1.918.258 3.76.75 5.5 1.446A21.727 21.727 0 0 0 18 11.25c0-2.414-.393-4.735-1.119-6.905ZM18.26 3.74a23.22 23.22 0 0 1 1.24 7.51 23.22 23.22 0 0 1-1.41 7.992.75.75 0 1 0 1.409.516 24.555 24.555 0 0 0 1.415-6.43 2.992 2.992 0 0 0 .836-2.078c0-.807-.319-1.54-.836-2.078a24.65 24.65 0 0 0-1.415-6.43.75.75 0 1 0-1.409.516c.059.16.116.321.17.483Z" /></svg>
                        <h2 class="sp-notice-title">お知らせ @if (count($dashboards) > 1)<span class="sp-notice-dashboard">{{ $notice['dashboard'] }}</span>@endif</h2>
                        @if ($notice['edit_url'] !== null)
                            <a href="{{ $notice['edit_url'] }}" class="sp-notice-edit" title="お知らせを編集" aria-label="お知らせを編集">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" /></svg>
                            </a>
                        @endif
                    </header>
                    <div class="sp-notice-body">
                        @if ($notice['html'] !== null)
                            <div class="sp-notice-content">{!! $notice['html'] !!}</div>
                        @else
                            <p class="sp-notice-none">お知らせはありません。</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <script>
        (() => {
            const clock = document.getElementById('sp-clock')
            const pad = (n) => String(n).padStart(2, '0')
            const tick = () => { const d = new Date(); clock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) }
            setInterval(tick, 15000)
        })()
    </script>
</x-core::layouts.account>
