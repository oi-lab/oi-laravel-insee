<?php

return [
    /*
    |--------------------------------------------------------------------------
    | INSEE API Client Secret
    |--------------------------------------------------------------------------
    |
    | Your INSEE API key for authentication. This is required to access
    | the SIRENE API. You can obtain this key from:
    | https://api.insee.fr/catalogue/
    |
    */
    'client_secret' => env('INSEE_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | INSEE API Client ID
    |--------------------------------------------------------------------------
    |
    | Your INSEE API client ID for OAuth authentication.
    | Only required if using OAuth authentication method.
    |
    */
    'client_id' => env('INSEE_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | INSEE API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the INSEE SIRENE API.
    | Default: https://api.insee.fr/api-sirene/3.11
    |
    */
    'base_url' => env('INSEE_BASE_URL', 'https://api.insee.fr/api-sirene/3.11'),

    /*
    |--------------------------------------------------------------------------
    | Access Token Cache Duration
    |--------------------------------------------------------------------------
    |
    | Duration in hours to cache the INSEE API access token.
    | Tokens are valid for 24 hours, so we cache for 23 hours by default.
    |
    */
    'cache_duration' => env('INSEE_CACHE_DURATION', 23),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | The Sirene API allows 30 calls per minute and 2 000 per hour (the INSEE
    | may change them: adjust here). One limiter shared by every call of the
    | package, kept in the cache store (use Redis in production, see the
    | `cache.limiter` setting).
    |
    | - background_ceiling: hourly calls after which searches and counts
    |   (searchEstablishmentsLazily(), countEstablishments()) stop with an
    |   InseeQuotaExceededException, keeping the rest of the hour for unit
    |   calls (findSiret(), establishmentOrFail()).
    | - max_wait_seconds: longest wait the typed methods accept when the minute
    |   window is full; beyond it they throw instead of sleeping.
    | - legacy_max_wait_seconds: longest wait of the historical methods, which
    |   then call anyway: they run in web requests and never throw.
    |
    */
    'rate_limits' => [
        'per_minute' => (int) env('INSEE_RATE_PER_MINUTE', 30),
        'per_hour' => (int) env('INSEE_RATE_PER_HOUR', 2000),
        'background_ceiling' => (int) env('INSEE_BACKGROUND_CEILING', 1600),
        'max_wait_seconds' => (int) env('INSEE_MAX_WAIT_SECONDS', 65),
        'legacy_max_wait_seconds' => (int) env('INSEE_LEGACY_MAX_WAIT_SECONDS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Typed methods retry on 5xx and network errors, waiting `delays` seconds
    | before each new attempt (3 retries: 1 s, 3 s, 9 s), never on 4xx. Set an
    | empty array to disable.
    |
    */
    'retry' => [
        'delays' => [1, 3, 9],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search requests
    |--------------------------------------------------------------------------
    |
    | post_threshold: URL length above which a search is sent as a POST
    | (application/x-www-form-urlencoded) instead of a GET.
    | mask_null_values: ask the API to omit null values (`masquerValeursNulles`).
    |
    */
    'post_threshold' => (int) env('INSEE_POST_THRESHOLD', 2000),

    'mask_null_values' => (bool) env('INSEE_MASK_NULL_VALUES', true),
];
