{{--
    製袋予定表 of 12. 製袋予定管理 (Modules\Production\Filament\Resources\BagSchedules\Board\BagScheduleBoard): the board of
    production-schedule.js, drawn and changed in the browser; its data come from this component ($wire). The script is
    loaded once ($script, versioned); the board is set up when shown and taken down when the view changes.
    Every statement ends with ";" (PHP drops the line break after printed values).
--}}
<div class="sfk-schedule-board">
    @assets
        <script src="{{ $script }}"></script>
    @endassets

    <div
        wire:ignore
        x-data="{
            board: null,
            init() {
                const options = @js($options);
                const start = () => {
                    if (! window.ProductionSchedule) { setTimeout(start, 30); return; }
                    if (! this.$refs.board.isConnected || this.board) return;
                    this.board = window.ProductionSchedule.mount(this.$refs.board, { ...options, ...window.ProductionSchedule.adapters.livewire($wire) });
                };
                start();
            },
            destroy() {
                if (this.board) this.board.destroy();
                this.board = null;
            },
        }"
    >
        <div x-ref="board"></div>
    </div>
</div>
