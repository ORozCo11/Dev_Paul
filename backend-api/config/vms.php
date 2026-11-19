<?php

return [
    // A passing readiness check stays valid until something changes (a ticket or a condition change invalidates it) or it is
    // this many days old. It used to lapse after a flat 24 hours, which left most of the fleet "not ready" with no real change.
    'readiness_max_days' => (int) env('READINESS_MAX_DAYS', 7),
];
