<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),

    /*
    | Whether every render checks that the compiled view is newer than its Blade file. Turn it off where views are
    | compiled ahead (`php artisan view:cache` on deploy) or cleared on change (the Docker dev watcher): a page renders
    | hundreds of views, each check is a file stat.
    */
    'check_cache_timestamps' => (bool) env('VIEW_CHECK_CACHE_TIMESTAMPS', true),

];
