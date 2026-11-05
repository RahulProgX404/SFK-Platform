<x-filament-panels::page>
    <style>
        /* the status board: one column per status, the apps as cards (tokens from core::filament.design) */
        .sfk-board { display: grid; grid-template-columns: repeat(6, minmax(180px, 1fr)); gap: var(--sfk-space-4); overflow-x: auto; padding-bottom: var(--sfk-space-2); }
        .sfk-column { --sfk-status: var(--sfk-subtle); display: flex; flex-direction: column; min-height: 22rem; background: var(--sfk-head); border-radius: var(--sfk-radius-lg); box-shadow: var(--sfk-ring); transition: opacity .15s, outline-color .15s, background-color .15s; outline: 2px dashed transparent; outline-offset: -4px; }
        .sfk-column.is-blocked { opacity: .35; }
        .sfk-column.is-target { outline-color: var(--sfk-primary); background: var(--sfk-primary-soft); }
        .sfk-column-head { display: flex; align-items: center; justify-content: space-between; gap: var(--sfk-space-2); padding: var(--sfk-space-3) var(--sfk-space-4); border-bottom: 1px solid var(--sfk-line); }
        .sfk-column-title { display: flex; white-space: nowrap; align-items: center; gap: var(--sfk-space-2); margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-bold); }
        .sfk-column-title::before { content: ""; width: 8px; height: 8px; border-radius: 9999px; background: var(--sfk-status); }
        .sfk-column-count { flex: none; white-space: nowrap; padding: 1px 8px; border-radius: 9999px; background: var(--sfk-surface); box-shadow: var(--sfk-ring); color: var(--sfk-muted); font-size: var(--sfk-fs-badge); font-variant-numeric: tabular-nums; }
        .sfk-cards { display: flex; flex-direction: column; gap: var(--sfk-space-2); padding: var(--sfk-space-3); flex: 1; }
        .sfk-card { padding: var(--sfk-space-3) var(--sfk-space-4); background: var(--sfk-surface); border-radius: var(--sfk-radius); box-shadow: var(--sfk-ring), var(--sfk-shadow); transition: box-shadow .15s; }
        .sfk-card[draggable="true"] { cursor: grab; }
        .sfk-card[draggable="true"]:hover { box-shadow: 0 0 0 1px var(--sfk-primary), var(--sfk-shadow-md); }
        .sfk-card.is-dragging { opacity: .5; }
        .sfk-card-name { margin: 0; color: var(--sfk-ink); font-size: var(--sfk-fs-label); font-weight: var(--sfk-fw-semibold); line-height: var(--sfk-lh-tight); }
        .sfk-card-code { margin: var(--sfk-space-1) 0 0; color: var(--sfk-muted); font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: var(--sfk-fs-badge); }
        .sfk-card-meta { margin: var(--sfk-space-2) 0 0; color: var(--sfk-text); font-size: var(--sfk-fs-small); }
        .sfk-card-actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sfk-space-1); margin-top: var(--sfk-space-3); padding-top: var(--sfk-space-2); border-top: 1px solid var(--sfk-line-soft); }
        .sfk-move { padding: 2px 8px; border-radius: var(--sfk-radius); background: var(--sfk-surface); box-shadow: 0 0 0 1px var(--sfk-line-strong); color: var(--sfk-text); font-size: var(--sfk-fs-badge); }
        .sfk-move:hover { box-shadow: 0 0 0 1px var(--sfk-primary); color: var(--sfk-primary); }
        .sfk-empty { margin: var(--sfk-space-2); color: var(--sfk-subtle); font-size: var(--sfk-fs-small); text-align: center; }
        .sfk-status-developing, .sfk-status-testing { --sfk-status: var(--sfk-subtle); }
        .sfk-status-ready { --sfk-status: var(--sfk-primary); }
        .sfk-status-published { --sfk-status: #16956b; }
        .sfk-status-maintenance { --sfk-status: #d48806; }
        .sfk-status-stopped { --sfk-status: var(--sfk-danger); }
    </style>

    <div
        x-data="{
            transitions: @js($transitions),
            dragging: null,
            from: null,
            over: null,
            canDrop(to) { return this.dragging !== null && (this.transitions[this.from] ?? []).includes(to) },
            drop(to) {
                if (this.canDrop(to)) { $wire.moveApp(this.dragging, to) }
                this.dragging = null; this.from = null; this.over = null
            },
        }"
        class="sfk-board"
        role="list"
        aria-label="アプリの状態"
    >
        @foreach ($columns as $column)
            @php($status = $column['status'])
            <section
                class="sfk-column sfk-status-{{ $status->value }}"
                :class="{ 'is-blocked': dragging !== null && from !== '{{ $status->value }}' && ! canDrop('{{ $status->value }}'), 'is-target': over === '{{ $status->value }}' && canDrop('{{ $status->value }}') }"
                x-on:dragover="if (canDrop('{{ $status->value }}')) { $event.preventDefault(); over = '{{ $status->value }}' }"
                x-on:dragleave="if (over === '{{ $status->value }}') over = null"
                x-on:drop.prevent="drop('{{ $status->value }}')"
                aria-label="{{ $status->label() }}"
            >
                <header class="sfk-column-head">
                    <h3 class="sfk-column-title">{{ $status->label() }}</h3>
                    <span class="sfk-column-count">{{ $column['apps']->count() }} 件</span>
                </header>

                <div class="sfk-cards">
                    @forelse ($column['apps'] as $app)
                        @php($targets = $status->targets($superAdmin))
                        <article
                            wire:key="app-{{ $app->id }}"
                            class="sfk-card"
                            draggable="{{ $targets === [] ? 'false' : 'true' }}"
                            :class="{ 'is-dragging': dragging === {{ $app->id }} }"
                            @if ($targets !== [])
                                x-on:dragstart="dragging = {{ $app->id }}; from = '{{ $status->value }}'; $event.dataTransfer.effectAllowed = 'move'"
                                x-on:dragend="dragging = null; from = null; over = null"
                            @endif
                        >
                            <p class="sfk-card-name">{{ $app->name }}</p>
                            <p class="sfk-card-code">{{ $app->code }}</p>
                            <p class="sfk-card-meta">
                                {{ $app->spaces->isNotEmpty() ? 'スペース：'.$app->spaces->pluck('name')->implode('、') : '未配置' }}
                            </p>

                            <div class="sfk-card-actions">
                                @foreach ($targets as $target)
                                    <button type="button" class="sfk-move" wire:click="moveApp({{ $app->id }}, '{{ $target->value }}')">
                                        → {{ $target->label() }}
                                    </button>
                                @endforeach
                                {{ ($this->editAppAction)(['app' => $app->id]) }}
                            </div>
                        </article>
                    @empty
                        <p class="sfk-empty">アプリはありません</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
