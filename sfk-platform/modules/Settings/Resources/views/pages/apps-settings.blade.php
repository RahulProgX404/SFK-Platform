<x-filament-panels::page>
    <style>
        /* legacy look (colours from core::filament.theme) */
        .sfk-board { display: grid; grid-template-columns: repeat(6, minmax(190px, 1fr)); gap: .75rem; overflow-x: auto; padding-bottom: .5rem; }
        .sfk-column { display: flex; flex-direction: column; min-height: 22rem; background: #f5f7f8; border: 1px solid var(--kt-border, #e3e7e8); transition: opacity .15s, outline-color .15s; outline: 2px dashed transparent; outline-offset: -4px; }
        .sfk-column.is-blocked { opacity: .35; }
        .sfk-column.is-target { outline-color: var(--kt-blue, #3498db); background: var(--kt-blue-soft, #ebf5fb); }
        .sfk-column-head { display: flex; align-items: center; justify-content: space-between; padding: .625rem .75rem; border-bottom: 1px solid var(--kt-border, #e3e7e8); background: #fff; }
        .sfk-column-title { margin: 0; font-size: .875rem; font-weight: 700; }
        .sfk-column-count { font-size: .75rem; color: var(--kt-muted, #888); }
        .sfk-cards { display: flex; flex-direction: column; gap: .5rem; padding: .5rem; flex: 1; }
        .sfk-card { background: #fff; border: 1px solid var(--kt-border, #e3e7e8); padding: .625rem .75rem; }
        .sfk-card[draggable="true"] { cursor: grab; }
        .sfk-card[draggable="true"]:hover { border-color: var(--kt-blue, #3498db); }
        .sfk-card.is-dragging { opacity: .5; }
        .sfk-card-name { margin: 0; font-size: .875rem; font-weight: 700; color: #333; }
        .sfk-card-code { margin: .125rem 0 0; font-size: .75rem; color: var(--kt-muted, #888); font-family: monospace; }
        .sfk-card-meta { margin: .375rem 0 0; font-size: .75rem; color: #555; }
        .sfk-card-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; margin-top: .5rem; }
        .sfk-move { font-size: .75rem; border: 1px solid var(--kt-border, #e3e7e8); background: #fff; padding: .125rem .5rem; }
        .sfk-move:hover { border-color: var(--kt-blue, #3498db); color: var(--kt-blue, #3498db); }
        .sfk-empty { margin: .5rem; font-size: .75rem; color: var(--kt-muted, #888); text-align: center; }
        .sfk-status-developing .sfk-column-head, .sfk-status-testing .sfk-column-head { border-top: 3px solid #95a5a6; }
        .sfk-status-ready .sfk-column-head { border-top: 3px solid var(--kt-blue, #3498db); }
        .sfk-status-published .sfk-column-head { border-top: 3px solid #27ae60; }
        .sfk-status-maintenance .sfk-column-head { border-top: 3px solid #f39c12; }
        .sfk-status-stopped .sfk-column-head { border-top: 3px solid var(--kt-red, #e74c3c); }
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
                                {{ $app->space !== null ? 'スペース：'.$app->space->name : '未配置' }}
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
