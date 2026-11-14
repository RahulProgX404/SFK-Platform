{{-- A term that explains itself (SFK UI 情報ツールチップ, Modules\Core\Filament\Support\Tip): dotted underline, the
     tooltip on hover, keyboard focus or a tap. Needs: $tip (Tip), $label. --}}
<span class="sfk-tip-term" tabindex="0" x-tooltip="{{ $tip->x() }}">{{ $label }}</span>
