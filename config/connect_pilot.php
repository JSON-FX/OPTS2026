<?php

return [
    'enabled' => (bool) env('CONNECT_PILOT_ENABLED', false),
    'connect_origin' => env('CONNECT_PILOT_ORIGIN'),
    'host_id' => env('CONNECT_PILOT_HOST_ID'),
    'key_id' => env('CONNECT_PILOT_KEY_ID'),
    'secret' => env('CONNECT_PILOT_SECRET'),
];
