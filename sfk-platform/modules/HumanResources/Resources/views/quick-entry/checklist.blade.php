{{--
    チェックリスト of 17. 安全衛生チェック in its quick entry (QuickSafetyCheck): the switch of the driving items 20–25, the count
    of the checked rows with 「すべてチェック」, then one card per item — No and 項目, 評価 はい / いいえ (a tap also checks the
    row), ✓ (check without changing 評価) and 備考 (opened by いいえ). The taps change the rows on the device (deferred), so
    the list answers at once; they are sent with 次へ. A row left without its チェック is marked after 次へ.
    $field, $input, $label, $error (core::filament.quick-entry)
--}}
@php
    use Modules\HumanResources\Domain\HrChoices;
    use Modules\HumanResources\Domain\SafetyChecks\ChecklistTemplate;

    $rows = array_values(array_filter((array) ($data['items'] ?? []), 'is_array'));
    $hasDriving = (bool) ($data['driving'] ?? false);
    $driving = ChecklistTemplate::DRIVING;
@endphp

<p class="sfk-qe-label" id="{{ $input }}-label">{{ $label }}</p>

<button type="button" class="sfk-qe-switch" role="switch" aria-checked="{{ $hasDriving ? 'true' : 'false' }}"
    wire:click="setDriving({{ $hasDriving ? 'false' : 'true' }})" wire:loading.attr="disabled">
    <span>運転をする（{{ $driving[0] }}〜{{ $driving[count($driving) - 1] }}）</span>
    <span @class(['sfk-qe-switch-track', 'is-on' => $hasDriving]) aria-hidden="true"><span></span></span>
</button>

<div class="sfk-qe-total" x-data="{ get done() { return ($wire.data.items || []).filter((row) => row && row.checked).length } }">
    <span class="sfk-qe-meter" aria-hidden="true"><span :style="'width: ' + ({{ max(1, count($rows)) }} ? Math.round(done / {{ max(1, count($rows)) }} * 100) : 0) + '%'"
        style="width: {{ count($rows) > 0 ? (int) round($this->checkedCount() / count($rows) * 100) : 0 }}%"></span></span>
    <strong><span x-text="done">{{ $this->checkedCount() }}</span> / {{ count($rows) }}</strong>
    <button type="button" class="sfk-qe-link" wire:click="checkAll">すべてチェック</button>
</div>

@if ($message = $error($field->name))
    <p class="sfk-qe-error" role="alert">{{ $message }}</p>
@endif

<ol class="sfk-qe-cards" aria-labelledby="{{ $input }}-label">
    @foreach ($rows as $index => $row)
        @php
            $key = 'data.items.'.$index;
            $checked = ($row['checked'] ?? false) === true;
            $invalid = $errors->has($key.'.checked');
            $no = is_numeric($row['number'] ?? null) ? (int) $row['number'] : null;
        @endphp
        <li wire:key="{{ $input }}-{{ $no ?? 'row' }}-{{ $index }}" @class(['sfk-qe-card', 'is-done' => $checked, 'is-invalid' => $invalid && ! $checked])
            x-data="{ notes: {{ filled($row['notes'] ?? null) || ($row['answer'] ?? null) === HrChoices::ANSWERS[1] ? 'true' : 'false' }}, get row() { return $wire.data.items[{{ $index }}] || {} } }"
            :class="{ 'is-done': row.checked, 'is-invalid': {{ $invalid ? 'true' : 'false' }} && ! row.checked }">
            <p class="sfk-qe-card-title" id="{{ $input }}-{{ $index }}">
                @if ($no !== null)
                    <span class="sfk-qe-no">{{ $no }}</span>
                @endif{{ $row['item'] ?? '' }}
            </p>
            <div class="sfk-qe-card-row" style="margin-top: var(--sfk-space-3)">
                <div class="sfk-qe-segment" role="radiogroup" aria-labelledby="{{ $input }}-{{ $index }}">
                    @foreach (HrChoices::ANSWERS as $answer)
                        <label>
                            <input type="radio" name="{{ $input }}-{{ $index }}-answer" value="{{ $answer }}" wire:model="{{ $key }}.answer"
                                x-on:click="$wire.set('{{ $key }}.checked', true, false); notes = notes || $event.target.value === @js(HrChoices::ANSWERS[1])"
                                @if ($loop->first && $index === 0) data-autofocus="desktop" @endif>
                            <span>{{ $answer }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="button" @class(['sfk-qe-check-toggle', 'is-on' => $checked]) :class="{ 'is-on': row.checked }"
                    :aria-pressed="row.checked ? 'true' : 'false'" aria-pressed="{{ $checked ? 'true' : 'false' }}"
                    x-on:click="$wire.set('{{ $key }}.checked', ! row.checked, false)" title="{{ $this->label('checked') }}">
                    <x-filament::icon icon="heroicon-m-check" />
                    <span>{{ $this->label('checked') }}</span>
                </button>
            </div>
            <div x-show="notes" x-cloak style="margin-top: var(--sfk-space-3)">
                <label class="sfk-qe-mini-label" for="{{ $input }}-{{ $index }}-notes">{{ $this->label('notes') }}</label>
                <textarea id="{{ $input }}-{{ $index }}-notes" rows="2" maxlength="4000" class="sfk-qe-textarea" wire:model="{{ $key }}.notes"></textarea>
            </div>
            <button type="button" class="sfk-qe-link sfk-qe-note-link" x-show="! notes" x-on:click="notes = true; $nextTick(() => document.getElementById('{{ $input }}-{{ $index }}-notes')?.focus())">
                {{ $this->label('notes') }}を入力
            </button>
        </li>
    @endforeach
</ol>

<button type="button" class="sfk-qe-add" wire:click="checkAll">
    <x-filament::icon icon="heroicon-m-check-badge" />
    すべてチェック
</button>
