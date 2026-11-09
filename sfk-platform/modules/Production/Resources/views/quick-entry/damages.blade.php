{{--
    損害 of 14. 事故報告管理 in its quick entry (QuickAccidentReport): the 合計損害額 on top (it stays in view while scrolling),
    one card per row — 項目 紙 / 印刷, 枚数 (枚), 原価 (@), 数量 and its 小計 (原価 × 数量, worked out as they are typed) —
    with 削除, and 「損害を追加」. No row is needed; rows left empty are not saved.
    $field, $input, $label, $error (core::filament.quick-entry)
--}}
@php
    use Modules\Core\Filament\Support\AppFormat;
    use Modules\Production\Domain\ProductionChoices;

    $rows = $this->damageRows();
    $rowError = fn (int $index, string $column): ?string => $errors->first('data.damages.'.$index.'.'.$column) ?: null;
@endphp

<p class="sfk-qe-label" id="{{ $input }}-label">{{ $label }}</p>

<div class="sfk-qe-total">
    <span>{{ $this->label('total_damage') }}</span>
    <strong>{{ AppFormat::yen($this->totalDamage()) }}</strong>
</div>

@if ($message = $error($field->name))
    <p class="sfk-qe-error" role="alert">{{ $message }}</p>
@endif

@if ($rows !== [])
    <ol class="sfk-qe-cards" aria-labelledby="{{ $input }}-label">
        @foreach ($rows as $index => $row)
            @php($key = 'data.damages.'.$index)
            <li class="sfk-qe-card" wire:key="{{ $input }}-{{ $index }}-{{ count($rows) }}">
                <div class="sfk-qe-card-head">
                    <p class="sfk-qe-card-title" id="{{ $input }}-{{ $index }}">{{ $label }} {{ $index + 1 }}</p>
                    <button type="button" class="sfk-qe-card-remove" wire:click="removeDamage({{ $index }})" title="{{ $label }} {{ $index + 1 }}を削除する">
                        <x-filament::icon icon="heroicon-m-trash" />
                        <span>削除</span>
                    </button>
                </div>

                <p class="sfk-qe-mini-label" id="{{ $input }}-{{ $index }}-item">{{ $this->label('item') }}</p>
                <div class="sfk-qe-segment" role="radiogroup" aria-labelledby="{{ $input }}-{{ $index }}-item">
                    @foreach (ProductionChoices::DAMAGE_ITEMS as $item)
                        <label>
                            <input type="radio" name="{{ $input }}-{{ $index }}-item" value="{{ $item }}" wire:model="{{ $key }}.item"
                                @if ($loop->first && $loop->parent->last) data-autofocus="desktop" @endif>
                            <span>{{ $item }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($message = $rowError($index, 'item'))
                    <p class="sfk-qe-error">{{ $message }}</p>
                @endif

                <div class="sfk-qe-card-grid">
                    <div>
                        <label class="sfk-qe-mini-label" for="{{ $input }}-{{ $index }}-sheets">{{ $this->label('sheets') }}</label>
                        <div class="sfk-qe-unit">
                            <input id="{{ $input }}-{{ $index }}-sheets" type="text" inputmode="decimal" autocomplete="off" x-on:focus="$el.select()"
                                @class(['sfk-qe-input', 'is-invalid' => $rowError($index, 'sheets')]) wire:model="{{ $key }}.sheets" enterkeyhint="next">
                            <span>枚</span>
                        </div>
                        @if ($message = $rowError($index, 'sheets'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="sfk-qe-mini-label" for="{{ $input }}-{{ $index }}-cost">{{ $this->label('cost') }}</label>
                        <div class="sfk-qe-unit">
                            <span>@</span>
                            <input id="{{ $input }}-{{ $index }}-cost" type="text" inputmode="decimal" autocomplete="off" x-on:focus="$el.select()"
                                @class(['sfk-qe-input', 'is-invalid' => $rowError($index, 'cost')]) wire:model.live.debounce.400ms="{{ $key }}.cost" enterkeyhint="next">
                        </div>
                        @if ($message = $rowError($index, 'cost'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="sfk-qe-mini-label" for="{{ $input }}-{{ $index }}-quantity">{{ $this->label('damage_quantity') }}</label>
                        <input id="{{ $input }}-{{ $index }}-quantity" type="text" inputmode="decimal" autocomplete="off" x-on:focus="$el.select()"
                            @class(['sfk-qe-input', 'is-invalid' => $rowError($index, 'quantity')]) wire:model.live.debounce.400ms="{{ $key }}.quantity" enterkeyhint="done">
                        @if ($message = $rowError($index, 'quantity'))
                            <p class="sfk-qe-error">{{ $message }}</p>
                        @endif
                    </div>
                </div>

                <div class="sfk-qe-card-foot">
                    <span>{{ $this->label('subtotal') }}</span>
                    <strong>{{ AppFormat::yen($this::subtotal($row)) }}</strong>
                </div>
            </li>
        @endforeach
    </ol>
@endif

<button type="button" class="sfk-qe-add" wire:click="addDamage" @if ($rows === []) data-autofocus="desktop" @endif>
    <x-filament::icon icon="heroicon-m-plus" />
    {{ $label }}を追加
</button>
