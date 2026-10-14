<x-filament-widgets::widget>
    <style>
        /* kintone app-tile look (colours from core::filament.kintone-theme) */
        .sfk-settings-menu { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: .75rem; }
        .sfk-settings-card {
            display: flex; flex-direction: column; gap: .625rem; padding: 1rem 1.125rem; border-radius: 0;
            background: #fff; border: 1px solid var(--kt-border, #e3e7e8); color: inherit; text-decoration: none;
        }
        .sfk-settings-card:hover { background: var(--kt-blue-soft, #ebf5fb); border-color: var(--kt-blue, #3498db); }
        .sfk-settings-card:focus-visible { outline: 2px solid var(--kt-blue, #3498db); outline-offset: 2px; }
        .sfk-settings-head { display: flex; align-items: center; gap: .75rem; }
        .sfk-settings-icon {
            display: grid; place-items: center; width: 3rem; height: 3rem; flex-shrink: 0; border-radius: 4px;
            background: var(--kt-blue, #3498db); color: #fff;
        }
        .sfk-settings-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--kt-blue, #3498db); }
        .sfk-settings-card:hover .sfk-settings-title { text-decoration: underline; }
        .sfk-settings-metric { margin: 0; font-size: .75rem; color: var(--kt-muted, #888); }
        .sfk-settings-description { margin: 0; font-size: .8125rem; line-height: 1.7; color: #555; flex: 1; }
        .sfk-settings-action { font-size: .8125rem; font-weight: 700; color: var(--kt-blue, #3498db); }
    </style>

    <x-filament::section heading="設定メニュー" description="管理する項目を選択してください。">
        <nav class="sfk-settings-menu" aria-label="設定メニュー">
            @foreach ($sections as $section)
                <a href="{{ $section['url'] }}" class="sfk-settings-card">
                    <div class="sfk-settings-head">
                        <span class="sfk-settings-icon" aria-hidden="true">
                            <x-filament::icon :icon="$section['icon']" style="width: 1.5rem; height: 1.5rem" />
                        </span>
                        <div>
                            <h3 class="sfk-settings-title">{{ $section['title'] }}</h3>
                            <p class="sfk-settings-metric">{{ $section['metric'] }}</p>
                        </div>
                    </div>
                    <p class="sfk-settings-description">{{ $section['description'] }}</p>
                    <span class="sfk-settings-action">{{ $section['action'] }} →</span>
                </a>
            @endforeach
        </nav>
    </x-filament::section>
</x-filament-widgets::widget>
