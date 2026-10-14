@php
    /** @var \App\Models\User $user */
    /** @var list<\Modules\Core\Http\Portal\DashboardEntry> $dashboards */
    $icons = [
        'erp' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
        'default' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
    ];
    // Flat app-icon colours, as on kintone's app tiles.
    $iconColors = ['erp' => '#3498db', 'settings' => '#7f8c8d'];
@endphp

<x-core::layouts.account title="ダッシュボード選択" :user="$user" :logout-url="$logoutUrl">
    <style>
        .kt-apps { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0; margin: 0; padding: 0; list-style: none; }
        .kt-app {
            display: flex; align-items: flex-start; gap: 14px; height: 100%; padding: 16px;
            color: var(--kt-text); border-bottom: 1px solid var(--kt-border); border-right: 1px solid var(--kt-border);
        }
        .kt-app:hover { background: var(--kt-blue-soft); text-decoration: none; }
        .kt-app:focus-visible { outline: 2px solid var(--kt-blue); outline-offset: -2px; }
        .kt-app-icon {
            display: grid; place-items: center; flex-shrink: 0; width: 48px; height: 48px; border-radius: 4px; color: #fff;
        }
        .kt-app-icon svg { width: 28px; height: 28px; }
        .kt-app-name { display: block; margin: 2px 0 4px; font-size: 15px; font-weight: 700; color: var(--kt-blue); }
        .kt-app:hover .kt-app-name { text-decoration: underline; }
        .kt-app-description { display: block; font-size: 12px; color: var(--kt-muted); line-height: 1.6; }
        .kt-apps-body { padding: 0; margin: -1px -1px 0 0; }
        .kt-empty { padding: 32px 16px; text-align: center; color: var(--kt-muted); }
    </style>

    @if (session('status'))
        <div class="kt-alert kt-alert-success" role="status">{{ session('status') }}</div>
    @endif

    <h1>{{ $user->name }} さん、ようこそ</h1>

    <section class="kt-box" aria-labelledby="dashboards-heading">
        <h2 class="kt-box-header" id="dashboards-heading" style="margin: 0">ダッシュボード</h2>

        @if ($dashboards === [])
            <p class="kt-empty">利用できるダッシュボードがありません。<br>システム管理者に利用権限の付与を依頼してください。</p>
        @else
            <div class="kt-apps-body">
                <ul class="kt-apps">
                    @foreach ($dashboards as $dashboard)
                        <li>
                            <a href="{{ $dashboard->url }}" class="kt-app">
                                <span class="kt-app-icon" style="background: {{ $iconColors[$dashboard->id] ?? '#3498db' }}" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">{!! $icons[$dashboard->icon] ?? $icons['default'] !!}</svg>
                                </span>
                                <span>
                                    <span class="kt-app-name">{{ $dashboard->label }}</span>
                                    @if ($dashboard->description !== '')
                                        <span class="kt-app-description">{{ $dashboard->description }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</x-core::layouts.account>
