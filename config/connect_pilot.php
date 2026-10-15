<?php

return [
    'enabled' => (bool) env('CONNECT_PILOT_ENABLED', false),
    'connect_origin' => env('CONNECT_PILOT_ORIGIN'),
    'host_id' => env('CONNECT_PILOT_HOST_ID'),
    'key_id' => env('CONNECT_PILOT_KEY_ID'),
    'secret' => env('CONNECT_PILOT_SECRET'),
    'introspection_source_per_minute' => max(300, min(60000, (int) env('CONNECT_PILOT_INTROSPECTION_SOURCE_PER_MINUTE', 6000))),
    'introspection_binding_per_minute' => max(30, min(600, (int) env('CONNECT_PILOT_INTROSPECTION_BINDING_PER_MINUTE', 120))),
];
