{{--
    新規 of 08. 日報入力 (Modules\Production\Filament\Resources\DailyReports\QuickEntry\QuickDailyReport): a panel over the
    whole screen, opened by the event of AppResource::QUICK_CREATE_EVENT with this app's code (新規 of the list, 新規作成 of
    the dashboard) or open from the start (?new=1). One step per screen with the progress on top, 戻る / 次へ at the bottom
    (Enter = 次へ); taps on a choice move on by themselves. Phones first: big touch targets, 16px+ inputs (no zoom), the
    keyboards of the values (numbers, times), no horizontal scroll; on wider screens the steps keep a phone-like column.
--}}
@php
    use Modules\Core\Filament\AppResource;
    use Modules\Production\Domain\ProductionChoices;
    use Modules\Orders\Domain\Orders\OrderChoices;
    use Modules\Production\Filament\Resources\DailyReports\QuickEntry\QuickDailyReport;

    $steps = $this->steps();
    $progress = $this->progress();
    $isStep = array_key_exists($screen, QuickDailyReport::STEPS);
    $first = $steps[0] ?? null;
    $error = fn (string $field): ?string => $errors->first('data.'.$field) ?: ($field === 'quality_checks' ? $errors->first('data.quality_checks.*') : null) ?: null;
    $titles = [
        'types' => $this->label('day_type').'・'.$this->label('work_type'),
        'damage' => $this->label('damaged_before').'・'.$this->label('damaged_after'),
        QuickDailyReport::REVIEW => '確認',
        QuickDailyReport::DETAILS => '追加情報',
    ];
    $title = $titles[$screen] ?? ($isStep ? $this->label(QuickDailyReport::STEPS[$screen][0]) : '');
@endphp

<div class="sfk-qe-root" x-data="{ shown: $wire.entangle('open') }"
    x-on:{{ AppResource::QUICK_CREATE_EVENT }}.window="if ($event.detail && $event.detail.app === @js($app)) { shown = true; $wire.start() }"
    x-on:keydown.escape.window="if (shown) { shown = false; $wire.close() }"
    x-effect="document.documentElement.style.overflow = shown ? 'hidden' : ''">
    <style>
        .sfk-qe {
            position: fixed;
            inset: 0;
            z-index: 45;
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            background: var(--sfk-page);
            color: var(--sfk-text);
        }

        .sfk-qe-head {
            display: grid;
            grid-template-columns: 48px 1fr 48px;
            align-items: center;
            gap: var(--sfk-space-2);
            padding: var(--sfk-space-2) var(--sfk-space-2);
            padding-top: max(var(--sfk-space-2), env(safe-area-inset-top));
            background: var(--sfk-surface);
            box-shadow: 0 1px 0 var(--sfk-line);
        }

        .sfk-qe-title {
            margin: 0;
            overflow: hidden;
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-title);
            font-weight: var(--sfk-fw-bold);
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sfk-qe-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border: 0;
            border-radius: var(--sfk-radius);
            background: transparent;
            color: var(--sfk-muted);
            cursor: pointer;
        }

        .sfk-qe-icon-btn:hover {
            background: var(--sfk-hover);
            color: var(--sfk-ink);
        }

        .sfk-qe-icon-btn .fi-icon {
            width: 24px;
            height: 24px;
        }

        .sfk-qe-progress {
            display: flex;
            align-items: center;
            gap: var(--sfk-space-3);
            padding: var(--sfk-space-2) var(--sfk-gutter);
            background: var(--sfk-surface);
            box-shadow: 0 1px 0 var(--sfk-line-soft);
        }

        .sfk-qe-bar {
            flex: 1;
            height: 6px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--sfk-line-soft);
        }

        .sfk-qe-bar>span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--sfk-primary);
            transition: width .25s ease;
        }

        .sfk-qe-count {
            color: var(--sfk-muted);
            font-size: var(--sfk-fs-small);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .sfk-qe-form {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 0;
        }

        .sfk-qe-body {
            flex: 1;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sfk-qe-step {
            box-sizing: border-box;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            padding: var(--sfk-space-5) var(--sfk-gutter) var(--sfk-space-8);
            animation: sfk-qe-forward .2s ease-out;
        }

        .sfk-qe-step.is-back {
            animation-name: sfk-qe-back;
        }

        @keyframes sfk-qe-forward {
            from {
                opacity: 0;
                transform: translateX(24px);
            }
        }

        @keyframes sfk-qe-back {
            from {
                opacity: 0;
                transform: translateX(-24px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sfk-qe-step {
                animation: none;
            }
        }

        .sfk-qe-label {
            display: block;
            margin: 0 0 var(--sfk-space-3);
            color: var(--kt-label, var(--sfk-ink));
            font-size: var(--sfk-fs-heading);
            font-weight: var(--sfk-fw-semibold);
        }

        .sfk-qe-field+.sfk-qe-field {
            margin-top: var(--sfk-space-5);
        }

        .sfk-qe-input,
        .sfk-qe-select,
        .sfk-qe-textarea {
            box-sizing: border-box;
            width: 100%;
            min-height: 56px;
            padding: 0 var(--sfk-space-4);
            border: 1px solid var(--sfk-line-strong);
            border-radius: var(--sfk-radius-lg);
            background: var(--sfk-surface);
            color: var(--sfk-ink);
            /* 16px or more: phones do not zoom into the field */
            font-size: var(--sfk-fs-figure);
            font-variant-numeric: tabular-nums;
        }

        .sfk-qe-textarea {
            min-height: 120px;
            padding: var(--sfk-space-3) var(--sfk-space-4);
            resize: vertical;
        }

        .sfk-qe-input:focus,
        .sfk-qe-select:focus,
        .sfk-qe-textarea:focus {
            border-color: var(--sfk-primary);
            outline: 2px solid var(--sfk-primary-soft-strong);
            outline-offset: 0;
        }

        .sfk-qe-input.is-invalid,
        .sfk-qe-select.is-invalid {
            border-color: var(--sfk-danger);
        }

        .sfk-qe-unit {
            display: flex;
            align-items: center;
            gap: var(--sfk-space-2);
        }

        .sfk-qe-unit>span {
            color: var(--sfk-muted);
            font-size: var(--sfk-fs-heading);
            white-space: nowrap;
        }

        .sfk-qe-error {
            margin: var(--sfk-space-2) 0 0;
            color: var(--sfk-danger-text);
            font-size: var(--sfk-fs-body);
        }

        .sfk-qe-chips {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: var(--sfk-space-2);
            margin-top: var(--sfk-space-3);
        }

        .sfk-qe-chip,
        .sfk-qe-option,
        .sfk-qe-toggle>span,
        .sfk-qe-segment span {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-height: 52px;
            padding: var(--sfk-space-2) var(--sfk-space-3);
            border: 1px solid var(--sfk-line-strong);
            border-radius: var(--sfk-radius-lg);
            background: var(--sfk-surface);
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-heading);
            font-variant-numeric: tabular-nums;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .sfk-qe-chip:hover,
        .sfk-qe-option:hover,
        .sfk-qe-toggle:hover>span,
        .sfk-qe-segment label:hover span {
            border-color: var(--sfk-primary);
        }

        .sfk-qe-chip.is-selected,
        .sfk-qe-option.is-selected,
        .sfk-qe-toggle input:checked+span,
        .sfk-qe-segment input:checked+span {
            border-color: var(--sfk-primary);
            background: var(--sfk-primary-soft);
            color: var(--sfk-primary);
            font-weight: var(--sfk-fw-semibold);
        }

        .sfk-qe-toggle input,
        .sfk-qe-segment input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .sfk-qe-toggle input:focus-visible+span,
        .sfk-qe-segment input:focus-visible+span {
            outline: 2px solid var(--sfk-primary);
            outline-offset: 2px;
        }

        .sfk-qe-toggle,
        .sfk-qe-segment label {
            position: relative;
            display: block;
        }

        .sfk-qe-options {
            display: grid;
            gap: var(--sfk-space-2);
            margin: var(--sfk-space-3) 0 0;
            padding: 0;
            list-style: none;
        }

        .sfk-qe-options.is-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .sfk-qe-option {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            text-align: left;
        }

        .sfk-qe-options.is-grid .sfk-qe-option {
            align-items: center;
            text-align: center;
        }

        .sfk-qe-option-main {
            overflow-wrap: anywhere;
        }

        .sfk-qe-option-sub {
            width: 100%;
            overflow: hidden;
            color: var(--sfk-muted);
            font-size: var(--sfk-fs-small);
            font-weight: var(--sfk-fw-regular);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sfk-qe-job {
            margin: 0 0 var(--sfk-space-4);
            padding: var(--sfk-space-3) var(--sfk-space-4);
            border-radius: var(--sfk-radius-lg);
            background: var(--sfk-surface);
            box-shadow: inset 0 0 0 1px var(--sfk-line);
            font-size: var(--sfk-fs-body);
        }

        .sfk-qe-job strong {
            display: block;
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-heading);
            font-weight: var(--sfk-fw-semibold);
        }

        .sfk-qe-job dl {
            display: flex;
            flex-wrap: wrap;
            gap: var(--sfk-space-1) var(--sfk-space-4);
            margin: var(--sfk-space-1) 0 0;
            color: var(--sfk-muted);
        }

        .sfk-qe-job dd {
            margin: 0 0 0 var(--sfk-space-1);
            color: var(--sfk-ink);
            font-variant-numeric: tabular-nums;
        }

        .sfk-qe-job dl>div {
            display: flex;
        }

        .sfk-qe-segment {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--sfk-space-2);
        }

        .sfk-qe-group {
            margin: 0;
            padding: 0;
            border: 0;
        }

        .sfk-qe-group+.sfk-qe-group {
            margin-top: var(--sfk-space-5);
        }

        .sfk-qe-pair {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--sfk-space-3);
        }

        .sfk-qe-toggles {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--sfk-space-2);
        }

        .sfk-qe-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            margin-top: var(--sfk-space-2);
            padding: 0 var(--sfk-space-2);
            border: 0;
            background: transparent;
            color: var(--sfk-primary);
            font-size: var(--sfk-fs-body);
            cursor: pointer;
        }

        .sfk-qe-summary {
            margin: 0;
            padding: 0;
            overflow: hidden;
            border-radius: var(--sfk-radius-lg);
            background: var(--sfk-surface);
            box-shadow: inset 0 0 0 1px var(--sfk-line);
            list-style: none;
        }

        .sfk-qe-summary li+li {
            border-top: 1px solid var(--sfk-line-soft);
        }

        .sfk-qe-summary-row {
            display: grid;
            grid-template-columns: minmax(96px, 38%) 1fr 20px;
            align-items: center;
            gap: var(--sfk-space-3);
            box-sizing: border-box;
            width: 100%;
            min-height: 52px;
            padding: var(--sfk-space-2) var(--sfk-space-4);
            border: 0;
            background: transparent;
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-body);
            text-align: left;
        }

        button.sfk-qe-summary-row {
            cursor: pointer;
        }

        button.sfk-qe-summary-row:hover {
            background: var(--sfk-hover);
        }

        .sfk-qe-summary-label {
            color: var(--kt-label, var(--sfk-ink));
            font-weight: var(--sfk-fw-medium);
        }

        .sfk-qe-summary-value {
            overflow-wrap: anywhere;
            font-variant-numeric: tabular-nums;
        }

        .sfk-qe-summary-row.is-invalid .sfk-qe-summary-value {
            color: var(--sfk-danger-text);
        }

        .sfk-qe-summary-row .fi-icon {
            width: 18px;
            height: 18px;
            color: var(--sfk-subtle);
        }

        .sfk-qe-check {
            display: flex;
            align-items: center;
            gap: var(--sfk-space-3);
            min-height: 52px;
            font-size: var(--sfk-fs-heading);
        }

        .sfk-qe-check input {
            width: 22px;
            height: 22px;
        }

        .sfk-qe-foot {
            display: flex;
            gap: var(--sfk-space-3);
            justify-content: center;
            padding: var(--sfk-space-3) var(--sfk-gutter);
            padding-bottom: max(var(--sfk-space-3), env(safe-area-inset-bottom));
            background: var(--sfk-surface);
            box-shadow: 0 -1px 0 var(--sfk-line);
        }

        .sfk-qe-foot-inner {
            display: flex;
            flex-wrap: wrap;
            gap: var(--sfk-space-3);
            width: 100%;
            max-width: 560px;
        }

        .sfk-qe-btn {
            display: inline-flex;
            flex: 1 1 0;
            align-items: center;
            justify-content: center;
            gap: var(--sfk-space-2);
            min-width: 0;
            min-height: 52px;
            padding: 0 var(--sfk-space-4);
            border: 1px solid var(--sfk-line-strong);
            border-radius: var(--sfk-radius-lg);
            background: var(--sfk-surface);
            color: var(--sfk-ink);
            font-size: var(--sfk-fs-heading);
            font-weight: var(--sfk-fw-semibold);
            white-space: nowrap;
            cursor: pointer;
        }

        .sfk-qe-btn.is-primary {
            flex-grow: 2;
            border-color: var(--sfk-primary);
            background: var(--sfk-primary);
            color: #fff;
        }

        .sfk-qe-btn:disabled {
            opacity: .6;
            cursor: wait;
        }

        .sfk-qe-foot-inner.is-stacked {
            flex-direction: column-reverse;
        }

        .sfk-qe-foot-inner.is-stacked .sfk-qe-btn {
            flex: none;
            width: 100%;
        }
    </style>

    <div class="sfk-qe" x-show="shown" x-cloak role="dialog" aria-modal="true" aria-labelledby="sfk-qe-title" x-transition.opacity.duration.150ms>
        <header class="sfk-qe-head">
            <span></span>
            <h2 class="sfk-qe-title" id="sfk-qe-title">日報を追加</h2>
            <button type="button" class="sfk-qe-icon-btn" x-on:click="shown = false; $wire.close()" title="閉じる">
                <x-filament::icon icon="heroicon-o-x-mark" />
                <span class="fi-sr-only">閉じる</span>
            </button>
        </header>

        <div class="sfk-qe-progress" aria-hidden="true">
            <div class="sfk-qe-bar"><span style="width: {{ $progress['percent'] }}%"></span></div>
            <span class="sfk-qe-count">{{ $isStep ? $progress['at'].' / '.$progress['of'] : $title }}</span>
        </div>

        <form class="sfk-qe-form" wire:submit="submit" novalidate>
            <div class="sfk-qe-body">
                <div @class(['sfk-qe-step', 'is-back' => $direction === 'back']) wire:key="sfk-qe-{{ $screen }}"
                    x-init="$nextTick(() => {
                        const field = $el.querySelector('[data-autofocus]');
                        if (field && (field.dataset.autofocus !== 'desktop' || window.matchMedia('(pointer: fine)').matches)) field.focus({ preventScroll: true });
                    })">

                    @if ($screen === 'order')
                        <label class="sfk-qe-label" for="sfk-qe-order">{{ $title }}</label>
                        @if ($order = $this->selectedOrder)
                            <div class="sfk-qe-job">
                                <strong>{{ $order['number'] }}</strong>
                                <span>{{ $order['product'] }}</span>
                                <dl>
                                    <div><dt>{{ $this->label('order_quantity') }}</dt><dd>{{ $order['quantity'] }}</dd></div>
                                    <div><dt>{{ $this->label('manufacturing_remaining') }}</dt><dd>{{ $order['remaining'] }}</dd></div>
                                    @if ($order['status'] !== '')
                                        <div><dt>{{ $this->label('current_status') }}</dt><dd>{{ $order['status'] }}</dd></div>
                                    @endif
                                </dl>
                            </div>
                        @endif
                        <input id="sfk-qe-order" type="search" class="sfk-qe-input" wire:model.live.debounce.250ms="orderSearch" placeholder="注番・品名で検索"
                            autocomplete="off" enterkeyhint="search" data-autofocus="desktop" x-on:keydown.enter.prevent>
                        @if ($message = $error('order_id'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                        <ul class="sfk-qe-options">
                            @foreach ($this->orderOptions as $option)
                                <li wire:key="sfk-qe-order-{{ $option['id'] }}">
                                    <button type="button" wire:click="choose('order_id', '{{ $option['id'] }}')"
                                        @class(['sfk-qe-option', 'is-selected' => (int) ($data['order_id'] ?? 0) === $option['id']])>
                                        <span class="sfk-qe-option-main">{{ $option['number'] }}</span>
                                        <span class="sfk-qe-option-sub">{{ $option['product'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($screen === 'machine')
                        <p class="sfk-qe-label" id="sfk-qe-machine">{{ $title }}</p>
                        @if ($message = $error('machine_id'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                        <ul class="sfk-qe-options is-grid" aria-labelledby="sfk-qe-machine">
                            @foreach ($this->machineOptions as $machineId => $machineName)
                                <li wire:key="sfk-qe-machine-{{ $machineId }}">
                                    <button type="button" wire:click="choose('machine_id', '{{ $machineId }}')" @if ($loop->first) data-autofocus="desktop" @endif
                                        @class(['sfk-qe-option', 'is-selected' => (int) ($data['machine_id'] ?? 0) === $machineId])>
                                        <span class="sfk-qe-option-main">{{ $machineName }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (in_array($screen, ['start', 'end', 'break'], true))
                        @php($field = QuickDailyReport::STEPS[$screen][0])
                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $title }}</label>
                        <input id="sfk-qe-{{ $field }}" type="time" step="60" @class(['sfk-qe-input', 'is-invalid' => $error($field)])
                            wire:model="data.{{ $field }}" enterkeyhint="next" data-autofocus>
                        @if ($message = $error($field))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                        <div class="sfk-qe-chips">
                            @foreach (QuickDailyReport::TIME_CHOICES[$field] as $choice)
                                <button type="button" wire:click="choose('{{ $field }}', '{{ $choice }}')"
                                    @class(['sfk-qe-chip', 'is-selected' => ($data[$field] ?? null) === $choice])>{{ $choice }}</button>
                            @endforeach
                        </div>
                    @elseif ($screen === 'types')
                        @foreach (['day_type' => ProductionChoices::DAY_TYPES, 'work_type' => ProductionChoices::WORK_TYPES] as $field => $choices)
                            @if ($this->editable($field))
                                <fieldset class="sfk-qe-group">
                                    <legend class="sfk-qe-label">{{ $this->label($field) }}</legend>
                                    <div class="sfk-qe-segment">
                                        @foreach ($choices as $choice)
                                            <label>
                                                <input type="radio" name="sfk-qe-{{ $field }}" value="{{ $choice }}" wire:model.live="data.{{ $field }}"
                                                    @if ($loop->first && $field === 'day_type') data-autofocus="desktop" @endif>
                                                <span>{{ $choice }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($message = $error($field))
                                        <p class="sfk-qe-error">{{ $message }}</p>
                                    @endif
                                </fieldset>
                            @endif
                        @endforeach
                        @if (is_numeric($this->hours['overtime']) && ($data['work_type'] ?? null) === ProductionChoices::WORK_OVERTIME)
                            <p class="sfk-qe-job">{{ $this->label('overtime_hours') }}　{{ rtrim(rtrim($this->hours['overtime'], '0'), '.') }}時間</p>
                        @endif
                    @elseif (in_array($screen, ['produced', 'crew', 'count'], true))
                        @php($field = QuickDailyReport::STEPS[$screen][0])
                        @php($unit = ['produced' => '枚', 'crew' => '人', 'count' => '回'][$screen])
                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $title }}</label>
                        <div class="sfk-qe-unit">
                            <input id="sfk-qe-{{ $field }}" type="text" inputmode="{{ $screen === 'produced' ? 'decimal' : 'numeric' }}" autocomplete="off"
                                @class(['sfk-qe-input', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}" enterkeyhint="next" data-autofocus
                                x-on:focus="$el.select()">
                            <span>{{ $unit }}</span>
                        </div>
                        @if ($message = $error($field))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                        @if ($screen === 'crew')
                            <div class="sfk-qe-chips">
                                @foreach (QuickDailyReport::CREW_CHOICES as $choice)
                                    <button type="button" wire:click="choose('crew', '{{ $choice }}')"
                                        @class(['sfk-qe-chip', 'is-selected' => (string) ($data['crew'] ?? '') === $choice])>{{ $choice }}{{ $unit }}</button>
                                @endforeach
                            </div>
                        @endif
                    @elseif ($screen === 'quality')
                        <p class="sfk-qe-label" id="sfk-qe-quality">{{ $title }}</p>
                        <div class="sfk-qe-toggles" role="group" aria-labelledby="sfk-qe-quality">
                            @foreach (ProductionChoices::QUALITY_CHECKS as $choice)
                                <label class="sfk-qe-toggle">
                                    <input type="checkbox" value="{{ $choice }}" wire:model="data.quality_checks" @if ($loop->first) data-autofocus="desktop" @endif>
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button type="button" class="sfk-qe-link" wire:click="checkAllQuality">すべて選択</button>
                        @if ($message = $error('quality_checks'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                    @elseif ($screen === 'damage')
                        <div class="sfk-qe-pair">
                            @foreach (['damaged_before', 'damaged_after'] as $field)
                                @if ($this->editable($field))
                                    <div>
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <div class="sfk-qe-unit">
                                            <input id="sfk-qe-{{ $field }}" type="text" inputmode="numeric" autocomplete="off" x-on:focus="$el.select()"
                                                @class(['sfk-qe-input', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}"
                                                @if ($loop->first) data-autofocus enterkeyhint="next" x-on:keydown.enter.prevent="document.getElementById('sfk-qe-damaged_after')?.focus()" @else enterkeyhint="done" @endif>
                                            <span>枚</span>
                                        </div>
                                        @if ($message = $error($field))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @elseif ($screen === QuickDailyReport::REVIEW)
                        <ul class="sfk-qe-summary">
                            @foreach ($this->summary() as $row)
                                <li>
                                    @if ($row['screen'] !== '')
                                        <button type="button" wire:click="goTo('{{ $row['screen'] }}')" title="{{ $row['label'] }}を変更する"
                                            @class(['sfk-qe-summary-row', 'is-invalid' => $row['value'] === '' && in_array($row['screen'], ['machine', 'produced', 'quality'], true)])>
                                            <span class="sfk-qe-summary-label">{{ $row['label'] }}</span>
                                            <span class="sfk-qe-summary-value">{{ $row['value'] !== '' ? $row['value'] : '-----' }}</span>
                                            <x-filament::icon icon="heroicon-m-chevron-right" />
                                        </button>
                                    @else
                                        <div class="sfk-qe-summary-row">
                                            <span class="sfk-qe-summary-label">{{ $row['label'] }}</span>
                                            <span class="sfk-qe-summary-value">{{ $row['value'] }}</span>
                                            <span></span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($screen === QuickDailyReport::DETAILS)
                        @foreach ($this->detailFields() as $field)
                            <div class="sfk-qe-field" wire:key="sfk-qe-detail-{{ $field }}">
                                @switch($field)
                                    @case('worked_on')
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <input id="sfk-qe-{{ $field }}" type="date" @class(['sfk-qe-input', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}">
                                        @break
                                    @case('user_id')
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <select id="sfk-qe-{{ $field }}" @class(['sfk-qe-select', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}">
                                            <option value="">-----</option>
                                            @foreach ($this->workerOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                    @case('cardboard_id')
                                    @case('partition_id')
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <select id="sfk-qe-{{ $field }}" @class(['sfk-qe-select', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}">
                                            <option value="">-----</option>
                                            @foreach ($this->boxOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                    @case('glue')
                                    @case('new_status')
                                    @case('difficulty')
                                        @php($choices = ['glue' => ProductionChoices::GLUES, 'new_status' => OrderChoices::STATUSES, 'difficulty' => ProductionChoices::DIFFICULTIES][$field])
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <select id="sfk-qe-{{ $field }}" @class(['sfk-qe-select', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}">
                                            <option value="">-----</option>
                                            @foreach ($choices as $choice)
                                                <option value="{{ $choice }}">{{ $choice }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                    @case('gloves')
                                    @case('update_status')
                                        <span class="sfk-qe-label">{{ $this->label($field) }}</span>
                                        <label class="sfk-qe-check">
                                            <input type="checkbox" class="sfk-ui-check" wire:model="data.{{ $field }}">
                                            <span>{{ $field === 'gloves' ? ProductionChoices::GLOVES : ProductionChoices::UPDATE_STATUS }}</span>
                                        </label>
                                        @break
                                    @case('notes')
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <textarea id="sfk-qe-{{ $field }}" rows="3" maxlength="4000" @class(['sfk-qe-textarea', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}"></textarea>
                                        @break
                                    @default
                                        @php($unit = ['changeover_minutes' => '分', 'cardboard_used' => '枚', 'partition_used' => '枚', 'glue_cost' => '円'][$field] ?? '')
                                        <label class="sfk-qe-label" for="sfk-qe-{{ $field }}">{{ $this->label($field) }}</label>
                                        <div class="sfk-qe-unit">
                                            <input id="sfk-qe-{{ $field }}" type="text" inputmode="decimal" autocomplete="off"
                                                @class(['sfk-qe-input', 'is-invalid' => $error($field)]) wire:model="data.{{ $field }}">
                                            @if ($unit !== '')
                                                <span>{{ $unit }}</span>
                                            @endif
                                        </div>
                                @endswitch
                                @if ($message = $error($field))
                                    <p class="sfk-qe-error">{{ $message }}</p>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <footer class="sfk-qe-foot">
                @if ($screen === QuickDailyReport::REVIEW)
                    <div class="sfk-qe-foot-inner is-stacked">
                        @if ($this->detailFields() !== [])
                            <button type="button" class="sfk-qe-btn" wire:click="showDetails" wire:loading.attr="disabled">追加情報を入力</button>
                        @endif
                        <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">この内容で登録</button>
                    </div>
                @elseif ($screen === QuickDailyReport::DETAILS)
                    <div class="sfk-qe-foot-inner">
                        <button type="button" class="sfk-qe-btn" wire:click="back">戻る</button>
                        <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">登録</button>
                    </div>
                @else
                    <div class="sfk-qe-foot-inner">
                        @if ($screen !== $first)
                            <button type="button" class="sfk-qe-btn" wire:click="back">戻る</button>
                        @endif
                        <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">次へ</button>
                    </div>
                @endif
            </footer>
        </form>
    </div>
</div>
