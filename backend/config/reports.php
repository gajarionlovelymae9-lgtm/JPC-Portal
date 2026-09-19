<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report Pagination
    |--------------------------------------------------------------------------
    |
    | Server-side pagination defaults and hard caps. `per_page` honours the
    | client value only when it falls inside the [1, max_per_page] range.
    |
    */

    'default_per_page' => 20,

    'max_per_page' => 100,

    /*
    |--------------------------------------------------------------------------
    | Trends & Rankings
    |--------------------------------------------------------------------------
    |
    | Monthly trend window bounds, and the cap on top-performer leaders.
    |
    */

    'trend_months' => 6,

    'trend_months_max' => 12,

    'top_performers_limit' => 5,

    'top_performers_limit_max' => 10,

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    |
    | Max rows written to a single exported file. Reports over this volume
    | must be narrowed with filters; the exporter streams whatever fits to
    | protect both memory and the client's download.
    |
    */

    'export_rows_max' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Aggregate Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | "Safe aggregates" — the expensive roll-ups behind trend / achievement /
    | target-vs-actual reports — are cached per (scope, filter-set) for a
    | short TTL. The 60s window bounds staleness well below a human noticing,
    | while the per-scope key prevents any cross-user/role cache leakage.
    |
    */

    'cache_ttl' => 60,

    'default_academic_year_id' => null,
];