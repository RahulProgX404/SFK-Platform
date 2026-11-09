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
    @include('core::filament.quick-entry-styles')

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
