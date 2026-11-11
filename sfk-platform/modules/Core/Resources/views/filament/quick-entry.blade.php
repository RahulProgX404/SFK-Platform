{{--
    新規 of an app as a quick entry (Modules\Core\Filament\QuickEntry\QuickEntry): a panel over the whole screen, opened by the
    event of AppResource::QUICK_CREATE_EVENT with the app's code (新規 of the list, 新規作成 of the dashboard) or open from
    the start (?new=1). One step per screen with the progress on top and 戻る / 次へ at the bottom (Enter = 次へ); a tap
    on a choice moves on by itself; the check lists the values, a tap on one goes back to its step. The inputs of the
    steps by kind (QuickField); `custom` ones are the app's own partials. Looks: core::filament.quick-entry-styles.
--}}
@php
    use Modules\Core\Filament\AppResource;
    use Modules\Core\Filament\QuickEntry\QuickEntry;
    use Modules\Core\Filament\QuickEntry\QuickField;

    // closed: nothing of the steps is built (their lists read the database) — the panel is drawn when it opens (speed)
    $steps = $open ? $this->steps() : [];
    $progress = $open ? $this->progress() : ['percent' => 0, 'at' => 0, 'of' => 0];
    $isStep = in_array($screen, $steps, true);
    $step = $isStep ? $this->stepOf($screen) : null;
    $fields = ! $open ? [] : ($isStep ? $this->fieldsOf($screen) : ($screen === QuickEntry::DETAILS ? $this->details() : []));
    $first = $steps[0] ?? null;
    $id = 'sfk-qe-'.$app;
    $error = fn (string $field): ?string => $errors->first('data.'.$field) ?: $errors->first('data.'.$field.'.*') ?: null;
    // the step asks one thing: its label is the title of the screen, not repeated above the input
    $single = $isStep && count($fields) === 1;
    $initials = fn (string $name): string => mb_substr(trim((string) preg_replace('/\s+/u', '', $name)), 0, 2);
@endphp

<div class="sfk-qe-root" x-data="{ shown: $wire.entangle('open') }"
    x-on:{{ AppResource::QUICK_CREATE_EVENT }}.window="if ($event.detail && $event.detail.app === @js($app)) { shown = true; $wire.start() }"
    x-on:keydown.escape.window="if (shown) { shown = false; $wire.close() }"
    x-effect="document.documentElement.style.overflow = shown ? 'hidden' : ''">
    @include('core::filament.quick-entry-styles')

    <div class="sfk-qe" x-show="shown" x-cloak role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" x-transition.opacity.duration.150ms>
        <header class="sfk-qe-head">
            <span></span>
            <h2 class="sfk-qe-title" id="{{ $id }}-title">{{ $this->heading() }}</h2>
            <button type="button" class="sfk-qe-icon-btn" x-on:click="shown = false; $wire.close()" title="閉じる">
                <x-filament::icon icon="heroicon-o-x-mark" />
                <span class="fi-sr-only">閉じる</span>
            </button>
        </header>

        @if ($open)
        <div class="sfk-qe-progress" aria-hidden="true">
            <div class="sfk-qe-bar"><span style="width: {{ $progress['percent'] }}%"></span></div>
            <span class="sfk-qe-count">{{ $isStep ? $progress['at'].' / '.$progress['of'] : $this->screenTitle() }}</span>
        </div>

        @endif

        @if ($open)
        <form class="sfk-qe-form" wire:submit="submit" novalidate>
            <div class="sfk-qe-body">
                <div @class(['sfk-qe-step', 'is-back' => $direction === 'back']) wire:key="{{ $id }}-{{ $screen }}"
                    x-init="$nextTick(() => {
                        const field = $el.querySelector('[data-autofocus]');
                        if (field && (field.dataset.autofocus !== 'desktop' || window.matchMedia('(pointer: fine)').matches)) field.focus({ preventScroll: true });
                    })">

                    @if ($screen === QuickEntry::REVIEW)
                        <ul class="sfk-qe-summary">
                            @foreach ($this->summary() as $row)
                                <li>
                                    @if ($row['screen'] !== '')
                                        <button type="button" wire:click="goTo('{{ $row['screen'] }}')" title="{{ $row['label'] }}を変更する"
                                            @class(['sfk-qe-summary-row', 'is-invalid' => $row['invalid']])>
                                            <span class="sfk-qe-summary-label">{{ $row['label'] }}</span>
                                            <span class="sfk-qe-summary-value">{{ $row['value'] !== '' ? $row['value'] : '-----' }}</span>
                                            <x-filament::icon icon="heroicon-m-chevron-right" />
                                        </button>
                                    @else
                                        <div @class(['sfk-qe-summary-row', 'is-figure'])>
                                            <span class="sfk-qe-summary-label">{{ $row['label'] }}</span>
                                            <span class="sfk-qe-summary-value">{{ $row['value'] }}</span>
                                            <span></span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        @if ($step !== null && $step->section !== '')
                            <p class="sfk-qe-section">{{ $step->section }}</p>
                        @endif

                        @foreach ($fields as $field)
                            @php
                                $name = $field->name;
                                $input = $id.'-'.$name;
                                $label = $single ? $step->title : $this->label($name);
                                $value = $data[$name] ?? null;
                                $autofocus = $loop->first;
                            @endphp
                            <div class="sfk-qe-field" wire:key="{{ $input }}">
                                @switch($field->kind)
                                    @case('choice')
                                        <p class="sfk-qe-label" id="{{ $input }}-label">{{ $label }}</p>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        <ul @class(['sfk-qe-options', 'is-cols-'.$field->columns => $field->columns > 1]) aria-labelledby="{{ $input }}-label">
                                            @foreach ($field->options as $optionValue => $optionLabel)
                                                <li wire:key="{{ $input }}-{{ $loop->index }}">
                                                    <button type="button" wire:click="choose(@js($name), @js((string) $optionValue))"
                                                        @if ($autofocus && $loop->first) data-autofocus="desktop" @endif
                                                        @class(['sfk-qe-option', 'is-selected' => $value !== null && (string) $value === (string) $optionValue])>
                                                        <span class="sfk-qe-option-main">{{ $optionLabel }}</span>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if (! $field->required && $value !== null && $value !== '')
                                            <button type="button" class="sfk-qe-link" wire:click="clear(@js($name))">選択を解除</button>
                                        @endif
                                        @break

                                    @case('choices')
                                        <p class="sfk-qe-label" id="{{ $input }}-label">{{ $label }}</p>
                                        <div @class(['sfk-qe-toggles']) role="group" aria-labelledby="{{ $input }}-label"
                                            style="grid-template-columns: repeat({{ max(1, $field->columns) }}, minmax(0, 1fr))">
                                            @foreach ($field->options as $optionValue => $optionLabel)
                                                <label class="sfk-qe-toggle">
                                                    <input type="checkbox" value="{{ $optionValue }}" wire:model="data.{{ $name }}" @if ($autofocus && $loop->first) data-autofocus="desktop" @endif>
                                                    <span>{{ $optionLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        @break

                                    @case('person')
                                    @case('people')
                                        @php($many = $field->kind === 'people')
                                        @php($chosen = array_map('strval', (array) $value))
                                        @if (count($field->options) > 6)
                                            <label class="sfk-qe-label" for="{{ $input }}-search">{{ $label }}</label>
                                            <input id="{{ $input }}-search" type="search" class="sfk-qe-input sfk-qe-search" wire:model.live.debounce.250ms="search"
                                                placeholder="名前で検索" autocomplete="off" enterkeyhint="search" x-on:keydown.enter.prevent
                                                @if ($autofocus) data-autofocus="desktop" @endif>
                                        @else
                                            <p class="sfk-qe-label">{{ $label }}</p>
                                        @endif
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        <ul class="sfk-qe-options">
                                            @forelse ($this->listed($field) as $personId => $personName)
                                                @php($selected = in_array((string) $personId, $chosen, true))
                                                <li wire:key="{{ $input }}-{{ $personId }}">
                                                    @if ($many)
                                                        <label @class(['sfk-qe-person', 'is-selected' => $selected])>
                                                            <input type="checkbox" class="fi-sr-only" value="{{ $personId }}" wire:model.live="data.{{ $name }}">
                                                            <span class="sfk-qe-avatar" aria-hidden="true">{{ $initials($personName) }}</span>
                                                            <span class="sfk-qe-person-name">{{ $personName }}</span>
                                                            @if ($selected)
                                                                <x-filament::icon icon="heroicon-m-check-circle" />
                                                            @endif
                                                        </label>
                                                    @else
                                                        <button type="button" wire:click="choose(@js($name), @js((string) $personId))"
                                                            @class(['sfk-qe-person', 'is-selected' => $selected])>
                                                            <span class="sfk-qe-avatar" aria-hidden="true">{{ $initials($personName) }}</span>
                                                            <span class="sfk-qe-person-name">{{ $personName }}</span>
                                                            @if ($selected)
                                                                <x-filament::icon icon="heroicon-m-check-circle" />
                                                            @endif
                                                        </button>
                                                    @endif
                                                </li>
                                            @empty
                                                <li><p class="sfk-qe-empty">該当なし</p></li>
                                            @endforelse
                                        </ul>
                                        @if (! $many && ! $field->required && $value !== null && $value !== '')
                                            <button type="button" class="sfk-qe-link" wire:click="clear(@js($name))">選択を解除</button>
                                        @endif
                                        @break

                                    @case('lookup')
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        @if ($picked = $this->lookupSelected($name))
                                            <div class="sfk-qe-job">
                                                <strong>{{ $picked['label'] }}</strong>
                                                <span>{{ $picked['sub'] }}</span>
                                                @if ($picked['facts'] !== [])
                                                    <dl>
                                                        @foreach ($picked['facts'] as $factLabel => $factValue)
                                                            <div><dt>{{ $factLabel }}</dt><dd>{{ $factValue }}</dd></div>
                                                        @endforeach
                                                    </dl>
                                                @endif
                                            </div>
                                        @endif
                                        <input id="{{ $input }}" type="search" class="sfk-qe-input" wire:model.live.debounce.250ms="search" placeholder="{{ $field->placeholder }}"
                                            autocomplete="off" enterkeyhint="search" x-on:keydown.enter.prevent @if ($autofocus) data-autofocus="desktop" @endif>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        <ul class="sfk-qe-options">
                                            @foreach ($this->lookupOptions($name, $search) as $option)
                                                <li wire:key="{{ $input }}-{{ $option['value'] }}">
                                                    <button type="button" wire:click="choose(@js($name), @js((string) $option['value']))"
                                                        @class(['sfk-qe-option', 'is-selected' => $value !== null && (string) $value === (string) $option['value']])>
                                                        <span class="sfk-qe-option-main">{{ $option['label'] }}</span>
                                                        @if ($option['sub'] !== '')
                                                            <span class="sfk-qe-option-sub">{{ $option['sub'] }}</span>
                                                        @endif
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if (! $field->required && $value !== null && $value !== '')
                                            <button type="button" class="sfk-qe-link" wire:click="clear(@js($name))">選択を解除</button>
                                        @endif
                                        @break

                                    @case('select')
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        <select id="{{ $input }}" @class(['sfk-qe-select', 'is-invalid' => $error($name)]) wire:model="data.{{ $name }}"
                                            @if ($autofocus && $isStep) data-autofocus="desktop" @endif>
                                            <option value="">-----</option>
                                            @foreach ($field->options as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        @break

                                    @case('date')
                                    @case('time')
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        <input id="{{ $input }}" type="{{ $field->kind }}" @if ($field->kind === 'time') step="60" @endif
                                            @class(['sfk-qe-input', 'is-invalid' => $error($name)]) wire:model="data.{{ $name }}" enterkeyhint="next"
                                            @if ($autofocus && $isStep) data-autofocus @endif>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        @if ($field->chips !== [])
                                            <div class="sfk-qe-chips">
                                                @foreach ($field->chips as $chipValue => $chipLabel)
                                                    <button type="button" wire:click="useChip(@js($name), @js((string) $chipValue))"
                                                        @class(['sfk-qe-chip', 'is-selected' => (string) $value === (string) $chipValue])>{{ $chipLabel }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @break

                                    @case('number')
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        <div class="sfk-qe-unit">
                                            <input id="{{ $input }}" type="text" inputmode="{{ $field->inputMode }}" autocomplete="off" x-on:focus="$el.select()"
                                                @class(['sfk-qe-input', 'is-invalid' => $error($name)]) wire:model="data.{{ $name }}" enterkeyhint="next"
                                                @if ($autofocus && $isStep) data-autofocus @endif>
                                            @if ($field->unit !== '')
                                                <span>{{ $field->unit }}</span>
                                            @endif
                                        </div>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        @if ($field->chips !== [])
                                            <div class="sfk-qe-chips">
                                                @foreach ($field->chips as $chipValue => $chipLabel)
                                                    <button type="button" wire:click="useChip(@js($name), @js((string) $chipValue))"
                                                        @class(['sfk-qe-chip', 'is-selected' => (string) $value === (string) $chipValue])>{{ $chipLabel }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @break

                                    @case('textarea')
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        <textarea id="{{ $input }}" rows="{{ $single ? 6 : 3 }}" maxlength="4000" @class(['sfk-qe-textarea', 'is-invalid' => $error($name)])
                                            wire:model="data.{{ $name }}" @if ($autofocus && $isStep) data-autofocus @endif></textarea>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                        @break

                                    @case('custom')
                                        @include($field->view, ['field' => $field, 'input' => $input, 'label' => $label, 'error' => $error])
                                        @break

                                    @default
                                        <label class="sfk-qe-label" for="{{ $input }}">{{ $label }}</label>
                                        <input id="{{ $input }}" type="text" autocomplete="off" @class(['sfk-qe-input', 'is-invalid' => $error($name)])
                                            wire:model="data.{{ $name }}" enterkeyhint="next" @if ($autofocus && $isStep) data-autofocus @endif>
                                        @if ($message = $error($name))
                                            <p class="sfk-qe-error">{{ $message }}</p>
                                        @endif
                                @endswitch
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <footer class="sfk-qe-foot">
                @if ($screen === QuickEntry::REVIEW)
                    <div class="sfk-qe-foot-inner is-stacked">
                        @if ($this->details() !== [])
                            <button type="button" class="sfk-qe-btn" wire:click="showDetails" wire:loading.attr="disabled">追加情報を入力</button>
                        @endif
                        <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">この内容で登録</button>
                    </div>
                @elseif ($screen === QuickEntry::DETAILS)
                    <div class="sfk-qe-foot-inner">
                        <button type="button" class="sfk-qe-btn" wire:click="back">戻る</button>
                        <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">登録</button>
                    </div>
                @else
                    <div @class(['sfk-qe-foot-inner', 'is-stacked' => $step?->skip !== null])>
                        @if ($step?->skip !== null)
                            <button type="button" class="sfk-qe-btn" wire:click="skip" wire:loading.attr="disabled">{{ $step->skip['label'] }}</button>
                        @endif
                        @if ($step?->skip !== null)
                            <div class="sfk-qe-foot-inner">
                                @if ($screen !== $first)
                                    <button type="button" class="sfk-qe-btn" wire:click="back">戻る</button>
                                @endif
                                <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">{{ $fromReview ? '確認へ' : '次へ' }}</button>
                            </div>
                        @else
                            @if ($screen !== $first)
                                <button type="button" class="sfk-qe-btn" wire:click="back">戻る</button>
                            @endif
                            <button type="submit" class="sfk-qe-btn is-primary" wire:loading.attr="disabled">{{ $fromReview ? '確認へ' : '次へ' }}</button>
                        @endif
                    </div>
                @endif
            </footer>
        </form>
        @endif
    </div>
</div>
