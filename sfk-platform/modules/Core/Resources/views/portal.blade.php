@php
    /** @var \App\Models\User $user */
    /** @var list<\Modules\Core\Http\Portal\DashboardEntry> $dashboards */
    /** @var list<array{dashboard: string, html: string|null, edit_url: string|null}> $notices */
    /** @var string|null $keyboardUrl Space / Enter opens this dashboard (白石封筒) */
    $icons = [
        'erp' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
        'default' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
    ];
    // Flat app-icon colours, as on the legacy system's app tiles.
    $iconColors = ['erp' => 'linear-gradient(135deg, #2a8cf0, #0d5fc4)', 'settings' => 'linear-gradient(135deg, #6b7b92, #4b5a70)'];

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
        /* image.png (user, 2026-11-05): the welcome card and 今日 (date, clock, 通知) side by side, then
           利用可能なシステム as a grid of cards (one per dashboard — more may come), then お知らせ */
        main { max-width: 1040px; }

        .sp-top { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; margin: 2px 0 28px; }
        @media (max-width: 860px) { .sp-top { grid-template-columns: minmax(0, 1fr); gap: 16px; margin-bottom: 24px; } }

        /* welcome card: ようこそ, the greeting with the name, a short blue rule; a desk with a laptop on the right */
        .sp-welcome { position: relative; display: flex; align-items: center; min-height: 186px; padding: 28px 32px; overflow: hidden; border-radius: 12px; background: linear-gradient(100deg, #f6f9fe 0%, #edf4fd 50%, #dceafb 100%); box-shadow: 0 0 0 1px #e1ecf7, 0 8px 24px rgba(5, 70, 126, .07); }
        .sp-welcome-text { position: relative; z-index: 1; min-width: 0; max-width: calc(100% - 250px); }
        .sp-eyebrow { margin: 0 0 6px; font-size: 12px; font-weight: 500; color: #1d6fd8; }
        .sp-welcome h1 { margin: 0; font-size: 28px; font-weight: 700; line-height: 1.35; color: var(--sfk-ink); }
        .sp-welcome h1 > span { white-space: nowrap; }
        .sp-welcome h1 small { font-size: 22px; font-weight: 700; }
        .sp-welcome-note { margin: 8px 0 0; font-size: 14px; color: #4b5563; }
        .sp-welcome-rule { display: block; width: 36px; height: 4px; margin-top: 20px; border-radius: 2px; background: #1d6fd8; }
        .sp-welcome-art { position: absolute; right: 12px; bottom: 0; width: 270px; height: 170px; pointer-events: none; }
                @media (max-width: 640px) {
            .sp-welcome { min-height: 0; padding: 22px 20px 24px; }
            .sp-welcome-text { max-width: none; }
            .sp-welcome h1 > span { white-space: normal; overflow-wrap: anywhere; }
            .sp-welcome-art { right: -40px; opacity: .25; }
        }

        /* 今日: date, clock and the bell's 通知 (not the お知らせ below) */
        .sp-today { display: flex; flex-direction: column; justify-content: center; gap: 12px; padding: 20px 22px; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px #e1ecf7, 0 8px 24px rgba(5, 70, 126, .07); font-variant-numeric: tabular-nums; }
        .sp-today-row { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 14px; color: var(--sfk-text); }
        .sp-today-row svg { width: 20px; height: 20px; flex: none; color: #1d6fd8; }
        .sp-today-row.is-time { font-size: 18px; font-weight: 600; color: var(--sfk-ink); }
        .sp-today-row.is-time svg { color: #5b6b82; }
        .sp-today hr { width: 100%; margin: 2px 0; border: 0; border-top: 1px solid #e4ebf3; }
        .sp-today-status { display: flex; align-items: flex-start; gap: 12px; }
        .sp-today-status > svg { width: 20px; height: 20px; flex: none; margin-top: 1px; color: #16a34a; }
        .sp-today-status.has-unread > svg { color: #d97706; }
        .sp-today-status p { margin: 0 0 6px; font-size: 13px; color: var(--sfk-text); }
        .sp-badge { display: inline-block; padding: 1px 10px; border-radius: 6px; background: #e7f6ec; color: #15803d; font-size: 12px; font-weight: 600; }
        .sp-today-status.has-unread .sp-badge { background: #fef3dc; color: #b45309; }

        /* 利用可能なシステム: a card per dashboard — two side by side, more wrap onto further columns / rows */
        .sp-systems { margin-bottom: 28px; }
        .sp-systems-head { display: flex; align-items: center; gap: 12px; margin: 0 0 14px; }
        .sp-systems-head > svg { width: 26px; height: 26px; flex: none; color: #1d6fd8; }
        .sp-systems-head h2 { flex: 1; min-width: 0; margin: 0; font-size: 17px; font-weight: 700; color: var(--sfk-ink); white-space: nowrap; }
        .sp-systems-count { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px 2px 4px; border-radius: 9999px; background: #fff; box-shadow: 0 0 0 1px #e1ecf7; font-size: 12px; color: var(--sfk-muted); white-space: nowrap; }
        .sp-systems-count b { display: grid; place-items: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 9999px; background: #e7f0fb; color: #1d6fd8; font-size: 11px; font-weight: 700; }
        .sp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 0; padding: 0; list-style: none; }
        .sp-card { display: flex; flex-direction: column; gap: 22px; height: 100%; padding: 22px 22px 18px; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px #e1ecf7, 0 8px 24px rgba(5, 70, 126, .06); color: var(--kt-text); transition: box-shadow .15s; }
        .sp-card:hover { text-decoration: none; box-shadow: 0 0 0 1px #8fbdf0, 0 10px 26px rgba(29, 111, 216, .14); }
        .sp-card:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: 2px; }
        .sp-card-top { display: flex; align-items: center; gap: 18px; }
        .sp-icon { display: grid; place-items: center; width: 60px; height: 60px; flex-shrink: 0; border-radius: 14px; color: #fff; box-shadow: 0 6px 14px rgba(16, 24, 40, .14); }
        .sp-icon svg { width: 30px; height: 30px; }
        .sp-name { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; }
        .sp-name strong { font-size: 18px; font-weight: 700; line-height: 1.35; color: var(--sfk-ink); overflow-wrap: anywhere; }
        .sp-name span { font-size: 13px; color: #4b5563; }
        .sp-arrow { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 9999px; background: #e7f0fb; color: #1d6fd8; transition: background-color .15s, color .15s; }
        .sp-card:hover .sp-arrow { background: #1d6fd8; color: #fff; }
        .sp-arrow svg { width: 16px; height: 16px; }
        .sp-card-open { display: inline-flex; align-items: center; gap: 8px; margin-top: auto; font-size: 13px; font-weight: 600; color: #1d6fd8; }
        .sp-card-open svg { width: 16px; height: 16px; transition: transform .15s; }
        .sp-card:hover .sp-card-open svg { transform: translateX(3px); }
        .sp-empty { margin: 0; padding: 28px 16px; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px #e1ecf7; text-align: center; font-size: 13px; color: var(--kt-muted); }
        @media (max-width: 520px) {
            .sp-systems-head { flex-wrap: wrap; gap: 6px 8px; }
            .sp-systems-head > svg { width: 22px; height: 22px; }
            .sp-systems-head h2 { flex: 1 0 auto; font-size: 16px; }
            .sp-systems-count { font-size: 11px; }
            .sp-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
            .sp-card { gap: 16px; padding: 16px 16px 14px; }
            .sp-card-top { gap: 14px; }
            .sp-icon { width: 48px; height: 48px; border-radius: 12px; }
            .sp-icon svg { width: 24px; height: 24px; }
            .sp-name strong { font-size: 16px; }
        }

        /* お知らせ: a blue header bar, then the text of the editor drawn as boxes, headings, chips and bullets */
        .sp-notices { display: flex; flex-direction: column; gap: 20px; }
        .sp-notice { overflow: hidden; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px #e1ecf7, 0 8px 24px rgba(5, 70, 126, .07); }
        .sp-notice-head { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; min-height: 62px; padding: 12px 20px; overflow: hidden; background: linear-gradient(100deg, #05467E 0%, #0d5fb3 55%, #2a83e0 100%); color: #fff; }
        .sp-notice-head::after { content: ""; position: absolute; right: -40px; top: -60px; width: 320px; height: 150px; background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, .12) 41%, rgba(255, 255, 255, .12) 55%, transparent 56%); pointer-events: none; }
        .sp-notice-icon { width: 30px; height: 30px; flex: none; color: #fff; }
        .sp-notice-title { margin: 0; font-size: 20px; font-weight: 700; line-height: 1.3; color: #fff; }
        .sp-notice-sub { padding-left: 12px; border-left: 1px solid rgba(255, 255, 255, .4); font-size: 12px; color: rgba(255, 255, 255, .92); }
        .sp-notice-dashboard { padding: 1px 10px; border-radius: 9999px; background: rgba(255, 255, 255, .18); font-size: 11px; font-weight: 600; }
        .sp-notice-edit { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 6px; margin-left: auto; height: 32px; padding: 0 16px; border-radius: 9999px; background: #fff; color: var(--sfk-ink); font-size: 13px; font-weight: 600; box-shadow: 0 2px 8px rgba(0, 0, 0, .12); }
        .sp-notice-edit:hover { background: #f3f7fb; text-decoration: none; }
        .sp-notice-edit svg { width: 15px; height: 15px; }
        .sp-notice-body { padding: 18px 20px 22px; }
        .sp-notice-none { margin: 0; font-size: 13px; color: var(--kt-muted); }
        @media (max-width: 640px) {
            .sp-notice-head { padding: 12px 14px; }
            .sp-notice-sub { display: none; }
            .sp-notice-body { padding: 14px 14px 18px; }
        }

        .sp-notice-content { font-size: 14px; line-height: 1.7; color: #1f2937; overflow-x: auto; }
        .sp-notice-content > * + * { margin-top: 10px; }
        .sp-notice-content p { margin: 0; }
        .sp-notice-content a { color: #1d6fd8; text-decoration: underline; }
        .sp-notice-content strong { font-weight: 700; }
        /* 文字色 of the editor: <span class="color" style="--color: …"> */
        .sp-notice-content .color { color: var(--color); }
        .sp-notice-content h2 { margin: 14px 0 4px; font-size: 18px; font-weight: 700; }
        /* 見出し (H3): a heading with a blue bar */
        .sp-notice-content h3 { display: flex; align-items: center; gap: 10px; margin: 18px 0 6px; font-size: 16px; font-weight: 700; color: var(--sfk-ink); }
        .sp-notice-content h3::before { content: ""; flex: none; width: 4px; height: 20px; border-radius: 2px; background: #1d6fd8; }
        /* 引用: a box — red with ! (an important point), amber with ⚠ when its text is orange / amber */
        .sp-notice-content blockquote { position: relative; margin: 0; padding: 10px 16px 10px 52px; border: 0; border-radius: 8px; background: #fde8e8; color: #dc2626; font-size: 15px; font-weight: 700; }
        .sp-notice-content blockquote::before { content: "!"; position: absolute; left: 14px; top: 50%; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 9999px; background: #e11d27; color: #fff; font-size: 16px; font-weight: 900; transform: translateY(-50%); }
        .sp-notice-content blockquote:has(.color[data-color="orange"], .color[data-color="amber"]) { background: #fef3dc; color: #1f2937; font-size: 14px; }
        .sp-notice-content blockquote:has(.color[data-color="orange"], .color[data-color="amber"])::before { content: ""; width: 28px; height: 25px; border-radius: 0; background: #f59e0b; clip-path: polygon(50% 0, 100% 100%, 0 100%); }
        .sp-notice-content blockquote:has(.color[data-color="orange"], .color[data-color="amber"])::after { content: "!"; position: absolute; left: 25px; top: 50%; color: #fff; font-size: 14px; font-weight: 900; transform: translateY(-36%); }
        .sp-notice-content blockquote p + p { margin-top: 4px; }
        /* ハイライト: a chip — the ※注 labels and marked words; orange when its text is orange / amber */
        .sp-notice-content mark { display: inline-block; padding: 0 8px; border-radius: 6px; background: #e3effb; color: #1d6fd8; font-weight: 700; line-height: 1.75; }
        .sp-notice-content mark:has(.color[data-color="orange"], .color[data-color="amber"]),
        .sp-notice-content .color[data-color="orange"] mark,
        .sp-notice-content .color[data-color="amber"] mark { background: #fdebd8; }
        .sp-notice-content mark .color { color: inherit; }
        .sp-notice-content mark:has(.color[data-color="orange"], .color[data-color="amber"]) { color: #ea7a17; }
        /* a line starting with a chip (※注1 …): indented, the chip and the text in two columns */
        .sp-notice-content > p:has(> mark:first-child) { margin: 6px 0 0 20px; }
        .sp-notice-content > p > mark:first-child { min-width: 60px; margin-right: 22px; text-align: center; }
        .sp-notice-content > h3 + p:has(> mark:first-child) { margin-top: 0; }
        .sp-notice-content hr { margin: 16px 0; border: 0; border-top: 1px solid #dfe5ec; }
        .sp-notice-content ul { margin: 12px 0 0; padding: 0; list-style: none; }
        .sp-notice-content ul li { position: relative; padding-left: 40px; }
        .sp-notice-content ul li + li { margin-top: 8px; }
        .sp-notice-content ul li::before { content: ""; position: absolute; left: 12px; top: .65em; width: 7px; height: 7px; border-radius: 9999px; background: #1d6fd8; }
        .sp-notice-content ol { list-style: decimal; padding-left: 24px; }
        .sp-notice-content table { border-collapse: collapse; margin: 8px 0; }
        .sp-notice-content td, .sp-notice-content th { border: 1px solid var(--kt-border); padding: 4px 8px; }
        /* phones: the ※注 chip above its text, no deep indents */
        @media (max-width: 520px) {
            .sp-notice-content { font-size: 13px; }
            .sp-notice-content blockquote { padding-left: 46px; font-size: 14px; }
            .sp-notice-content blockquote::before { left: 12px; }
            .sp-notice-content > p:has(> mark:first-child) { margin-left: 0; }
            .sp-notice-content > p > mark:first-child { display: table; min-width: 0; margin: 0 0 2px; }
            .sp-notice-content ul li { padding-left: 22px; }
            .sp-notice-content ul li::before { left: 6px; }
        }
    </style>

    @if (session('status'))
        <div class="kt-alert kt-alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="sp-top">
        <section class="sp-welcome" aria-label="ようこそ">
            <div class="sp-welcome-text">
                <p class="sp-eyebrow">ようこそ</p>
                <h1><span>{{ $greeting }}、</span><span>{{ $user->name }} <small>さん</small></span></h1>
                <p class="sp-welcome-note">今日もよろしくお願いします。</p>
                <span class="sp-welcome-rule" aria-hidden="true"></span>
            </div>

            {{-- a desk with a laptop, a plant and a cup --}}
            <svg class="sp-welcome-art" viewBox="0 0 270 170" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="sp-glow" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#cfe3fb" stop-opacity="0" /><stop offset="1" stop-color="#b9d6f8" stop-opacity=".9" /></linearGradient>
                    <linearGradient id="sp-screen" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f4f9ff" /><stop offset="1" stop-color="#dcebfd" /></linearGradient>
                    <linearGradient id="sp-body" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3a6ea8" /><stop offset="1" stop-color="#22507f" /></linearGradient>
                </defs>
                <ellipse cx="150" cy="150" rx="120" ry="34" fill="url(#sp-glow)" />
                <g fill="#ffffff" fill-opacity=".7"><circle cx="40" cy="34" r="3" /><circle cx="236" cy="22" r="4" /><circle cx="214" cy="56" r="2.5" /></g>
                {{-- laptop --}}
                <rect x="86" y="30" width="134" height="92" rx="7" fill="url(#sp-body)" />
                <rect x="92" y="36" width="122" height="80" rx="3" fill="url(#sp-screen)" />
                <rect x="92" y="36" width="122" height="11" rx="3" fill="#3b82f6" />
                <g fill="#9cc5f6"><rect x="100" y="55" width="44" height="6" rx="3" /><rect x="100" y="66" width="62" height="5" rx="2.5" /><rect x="100" y="76" width="54" height="5" rx="2.5" /></g>
                <rect x="100" y="88" width="50" height="20" rx="3" fill="#ffffff" stroke="#c9def7" />
                <g fill="#3b82f6"><rect x="168" y="86" width="8" height="22" rx="2" /><rect x="180" y="78" width="8" height="30" rx="2" /><rect x="192" y="92" width="8" height="16" rx="2" fill="#93c5fd" /></g>
                <rect x="168" y="56" width="36" height="16" rx="3" fill="#ffffff" stroke="#c9def7" />
                <path d="M74 122 H232 L244 134 Q244 138 240 138 H66 Q62 138 62 134 Z" fill="#d7e3f1" />
                <path d="M136 122 H170 L167 127 H139 Z" fill="#b8c9dc" />
                {{-- plant --}}
                <path d="M40 104 C28 88 30 66 36 54 C44 70 46 88 44 104 Z" fill="#5cc28f" />
                <path d="M46 104 C46 82 56 66 66 60 C66 80 58 96 50 106 Z" fill="#34a873" />
                <path d="M38 106 C24 100 16 88 14 78 C28 82 38 92 42 106 Z" fill="#7fd3a6" />
                <rect x="28" y="104" width="30" height="34" rx="6" fill="#ffffff" stroke="#cfe0f5" />
                {{-- cup --}}
                <rect x="246" y="116" width="20" height="22" rx="4" fill="#ffffff" stroke="#cfe0f5" />
                <path d="M266 121 h3 a5 5 0 0 1 0 10 h-3" fill="none" stroke="#cfe0f5" stroke-width="2.5" />
                <path d="M251 110 c0 -4 4 -4 4 -8 M258 110 c0 -4 4 -4 4 -8" fill="none" stroke="#b9d6f8" stroke-width="1.6" stroke-linecap="round" />
            </svg>
        </section>

        <section class="sp-today" aria-label="今日">
            <p class="sp-today-row">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" /></svg>
                {{ $now->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}
            </p>
            <p class="sp-today-row is-time">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                {{-- a clock that keeps going --}}
                <time id="sp-clock">{{ $now->format('H:i') }}</time>
            </p>
            <hr>
            {{-- the bell's 通知 (not the お知らせ below): how many are unread --}}
            <div @class(['sp-today-status', 'has-unread' => $unread > 0])>
                @if ($unread > 0)
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a6 6 0 0 0-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 0 0 .515 1.076 32.91 32.91 0 0 0 3.256.508 3.5 3.5 0 0 0 6.972 0 32.903 32.903 0 0 0 3.256-.508.75.75 0 0 0 .515-1.076A11.448 11.448 0 0 1 16 8a6 6 0 0 0-6-6ZM8.05 14.943a33.54 33.54 0 0 0 3.9 0 2 2 0 0 1-3.9 0Z" clip-rule="evenodd" /></svg>
                    <div>
                        <p>未読の通知があります</p>
                        <span class="sp-badge">未読の通知 {{ number_format($unread) }}件</span>
                    </div>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                    <div>
                        <p>未読の通知はありません</p>
                        <span class="sp-badge">未読の通知なし</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="sp-systems" aria-labelledby="systems-heading">
        <div class="sp-systems-head">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2" /><rect x="13" y="3" width="8" height="8" rx="2" /><rect x="3" y="13" width="8" height="8" rx="2" /><rect x="13" y="13" width="8" height="8" rx="2" /></svg>
            <h2 id="systems-heading">利用可能なシステム</h2>
            <span class="sp-systems-count"><b>{{ count($dashboards) }}</b>個のアプリケーション</span>
        </div>

        @if ($dashboards === [])
            <p class="sp-empty">利用できるダッシュボードがありません。</p>
        @else
            <ul class="sp-grid">
                @foreach ($dashboards as $dashboard)
                    <li>
                        <a href="{{ $dashboard->url }}" class="sp-card">
                            <span class="sp-card-top">
                                <span class="sp-icon" style="background: {{ $iconColors[$dashboard->icon] ?? $iconColors['erp'] }}" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">{!! $icons[$dashboard->icon] ?? $icons['default'] !!}</svg>
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
                            </span>
                            <span class="sp-card-open">アプリケーションを開く
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
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
                        <h2 class="sp-notice-title">お知らせ</h2>
                        <span class="sp-notice-sub">社内連絡・重要なお知らせ</span>
                        @if (count($dashboards) > 1)
                            <span class="sp-notice-dashboard">{{ $notice['dashboard'] }}</span>
                        @endif
                        @if ($notice['edit_url'] !== null)
                            <a href="{{ $notice['edit_url'] }}" class="sp-notice-edit" title="お知らせを編集">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" /></svg>
                                編集
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

    @if ($keyboardUrl !== null)
        {{-- Space / Enter opens 白石封筒 (the main dashboard) — not while a link, button or field has the focus (they keep
             their own Enter / Space), nor with a modifier key, a held-down key or while typing Japanese (IME).
             Every statement ends with ";": PHP drops the line break after the URL printed by @js. --}}
        <script>
            (() => {
                const url = @js($keyboardUrl);
                const own = 'a[href], button, input, textarea, select, summary, label, [contenteditable]:not([contenteditable="false"]), [role="button"], [role="link"], [role="menuitem"], [role="textbox"]';
                let opening = false;
                window.addEventListener('pageshow', () => { opening = false; });
                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Spacebar') return;
                    if (opening || event.defaultPrevented || event.repeat || event.isComposing || event.keyCode === 229) return;
                    if (event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
                    const target = event.target instanceof Element ? event.target : null;
                    if (target && target !== document.body && target.closest(own)) return;
                    event.preventDefault();
                    opening = true;
                    window.location.assign(url);
                });
            })();
        </script>
    @endif
</x-core::layouts.account>
