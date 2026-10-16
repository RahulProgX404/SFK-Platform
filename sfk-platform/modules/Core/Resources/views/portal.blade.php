@php
    /** @var \App\Models\User $user */
    /** @var list<\Modules\Core\Http\Portal\DashboardEntry> $dashboards */
    $icons = [
        'erp' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
        'default' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
    ];
    // Flat app-icon colours, as on the legacy system's app tiles.
    $iconColors = ['erp' => '#3498db', 'settings' => '#7f8c8d'];
@endphp

<x-core::layouts.account title="ダッシュボード選択" :user="$user">
    <style>
        /* Dashboard selection in the legacy vendor's flat style: hairline boxes, square icon tiles, light-blue hover */
        main { max-width: 1120px; }
        .sp-hero { margin: 4px 0 24px; padding: 16px 20px; background: #fff; border: 1px solid var(--kt-border); border-left: 5px solid var(--kt-blue); }
        .sp-hero h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .sp-hero p { margin: 4px 0 0; font-size: 13px; color: var(--kt-muted); }

        .sp-heading { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-size: 16px; font-weight: 700; }
        .sp-heading span { padding: 0 8px; background: var(--kt-blue); color: #fff; font-size: 12px; font-weight: 400; }

        .sp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin: 0; padding: 0; list-style: none; }
        .sp-card { display: flex; align-items: center; gap: 16px; height: 100%; padding: 16px; background: #fff; border: 1px solid var(--kt-border); color: var(--kt-text); }
        .sp-card:hover { text-decoration: none; background: var(--kt-blue-soft); border-color: var(--kt-blue); }
        .sp-card:focus-visible { outline: 1px solid var(--kt-blue); outline-offset: 1px; }
        .sp-icon { display: grid; place-items: center; width: 56px; height: 56px; flex-shrink: 0; color: #fff; }
        .sp-icon svg { width: 30px; height: 30px; }
        .sp-name { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 8px; min-width: 0; font-size: 16px; font-weight: 700; color: var(--kt-blue); }
        .sp-arrow { display: grid; place-items: center; width: 24px; height: 24px; flex-shrink: 0; color: var(--kt-blue); }
        .sp-arrow svg { width: 16px; height: 16px; }

        .sp-empty { padding: 48px 16px; border: 1px solid var(--kt-border); background: #fff; text-align: center; color: var(--kt-muted); }
    </style>

    @if (session('status'))
        <div class="kt-alert kt-alert-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="sp-hero">
        <h1>{{ $user->name }} さん、ようこそ</h1>
        <p>{{ now()->locale('ja')->isoFormat('YYYY年M月D日（ddd）') }}</p>
    </section>

    <section aria-labelledby="dashboards-heading">
        <h2 class="sp-heading" id="dashboards-heading">ダッシュボード <span>{{ count($dashboards) }}</span></h2>

        @if ($dashboards === [])
            <p class="sp-empty">利用できるダッシュボードがありません。</p>
        @else
            <ul class="sp-grid">
                @foreach ($dashboards as $dashboard)
                    @php($color = $iconColors[$dashboard->id] ?? '#3498db')
                    <li>
                        <a href="{{ $dashboard->url }}" class="sp-card">
                            <span class="sp-icon" style="background: {{ $color }}" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">{!! $icons[$dashboard->icon] ?? $icons['default'] !!}</svg>
                            </span>
                            <span class="sp-name">
                                {{ $dashboard->label }}
                                <span class="sp-arrow" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-core::layouts.account>
