<?php

// Only what differs from Filament's defaults (vendor/filament/support/config/filament.php).
return [
    // Docker keeps the component cache on the container's own filesystem (docker-compose.yml).
    'cache_path' => env('FILAMENT_CACHE_PATH', base_path('bootstrap/cache/filament')),
];
