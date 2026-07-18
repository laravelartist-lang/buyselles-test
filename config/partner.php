<?php

return [
    'sync_fulfillment_timeout' => (int) env('PARTNER_SYNC_FULFILLMENT_TIMEOUT', 60),
    'sync_poll_interval_ms' => (int) env('PARTNER_SYNC_POLL_INTERVAL_MS', 500),
];
