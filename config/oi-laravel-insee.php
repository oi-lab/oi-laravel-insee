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
];
