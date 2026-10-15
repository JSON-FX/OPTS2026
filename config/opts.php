<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle Threshold Days
    |--------------------------------------------------------------------------
    |
    | The number of business days a transaction can sit at a step without
    | any action before it is considered "stagnant". Used by the ETA
    | calculation service for delay detection.
    |
    */

    'idle_threshold_days' => (int) env('OPTS_IDLE_THRESHOLD_DAYS', 2),

    // Published deployment notices. Each requires id, kind, title and message.
    // Optional starts_at/ends_at schedule visibility; detail, audience and publisher are plain text.
    'announcements' => [],

];
