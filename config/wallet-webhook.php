<?php

return [
    /*
     * Master switch. Off by default so the feature can be deployed dark.
     */
    'enabled' => (bool) env('KADI_SITE_WEBHOOK_ENABLED', false),

    /*
     * Receiving endpoint on the Kadi player site.
     */
    'url' => env('KADI_SITE_WEBHOOK_URL'),

    /*
     * Shared HMAC signing secret. At least 32 random bytes.
     */
    'secret' => env('KADI_SITE_WEBHOOK_SECRET'),

    /*
     * Burst window: balance changes on the same wallet within this many
     * seconds are coalesced into a single send of the latest state.
     */
    'debounce_seconds' => (int) env('KADI_SITE_WEBHOOK_DEBOUNCE_SECONDS', 2),

    'http_timeout' => 5,

    'http_connect_timeout' => 3,
];
