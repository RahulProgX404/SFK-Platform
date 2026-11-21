{{--
    Marker of a live page (Modules\Core\Application\Realtime\DataPulse): the stamps of the tables it shows as they were
    when it was drawn. Put it inside the page's Livewire component; core::filament.live-data-script refreshes that
    component when the pulse brings other stamps, and the redrawn marker carries the new ones.
    $tables: list<string> (watched by the module, DataPulse::watch())
--}}
<span hidden data-sfk-live="{{ json_encode(\Modules\Core\Application\Realtime\DataPulse::versions($tables), JSON_FORCE_OBJECT) }}"></span>
