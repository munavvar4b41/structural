<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'http://localhost:1420',
        'http://127.0.0.1:1420',
        'https://tauri.localhost',
        'tauri://localhost',
        'http://structural.local',
        env('DESKTOP_CORS_ORIGIN'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
