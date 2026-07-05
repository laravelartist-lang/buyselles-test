<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supplier stock cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long to cache remote stock counts fetched from supplier APIs when
    | rendering cart quantity limits or validating cart updates.
    |
    */
    'stock_cache_ttl' => (int) env('SUPPLIER_STOCK_CACHE_TTL', 60),
];
