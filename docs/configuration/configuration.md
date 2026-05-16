---
title: Configuration
description: Configure the OI Laravel INSEE package
order: 1
---

# Configuration

After publishing the package configuration, you'll have a `config/insee.php` file in your project. This file contains all the settings needed to connect to the INSEE SIRENE API.

## Configuration File

Here's the full configuration file with all available options:

```php
<?php

return [
    /*
     * INSEE API Client Secret
     * 
     * This is your OAuth client secret obtained from the INSEE Portail API.
     * Required for all API requests.
     * 
     * Set via environment variable: INSEE_CLIENT_SECRET
     */
    'client_secret' => env('INSEE_CLIENT_SECRET'),

    /*
     * INSEE API Client ID
     * 
     * Optional OAuth client ID for more robust authentication.
     * If not provided, the package uses basic authentication with the client secret.
     * 
     * Set via environment variable: INSEE_CLIENT_ID
     */
    'client_id' => env('INSEE_CLIENT_ID'),

    /*
     * INSEE API Base URL
     * 
     * The base URL for the INSEE SIRENE API.
     * Generally should not be changed unless you're testing against a different endpoint.
     * 
     * Set via environment variable: INSEE_BASE_URL
     * Default: https://api.insee.fr/api-sirene/3.11
     */
    'base_url' => env('INSEE_BASE_URL', 'https://api.insee.fr/api-sirene/3.11'),

    /*
     * Token Cache Duration (in seconds)
     * 
     * How long to cache the OAuth token before requesting a new one.
     * Default is 82800 seconds (23 hours), which respects INSEE's typical token expiry.
     * 
     * Set via environment variable: INSEE_CACHE_DURATION
     * Default: 82800 (23 hours)
     */
    'cache_duration' => env('INSEE_CACHE_DURATION', 82800),
];
```

## Configuration Options

### client_secret (Required)

Your INSEE API client secret obtained from the [INSEE Portail API](https://portail-api.insee.fr). This is required for all API requests.

**Environment Variable:** `INSEE_CLIENT_SECRET`

```env
INSEE_CLIENT_SECRET=your_secret_key_from_insee
```

### client_id (Optional)

Optional OAuth client ID for more robust authentication. If not provided, the package uses basic authentication with the client secret alone.

**Environment Variable:** `INSEE_CLIENT_ID`

```env
INSEE_CLIENT_ID=your_client_id_from_insee
```

### base_url

The base URL for the INSEE SIRENE API. The default URL points to version 3.11 of the API.

**Environment Variable:** `INSEE_BASE_URL`

**Default:** `https://api.insee.fr/api-sirene/3.11`

You typically don't need to change this unless you're testing against a different endpoint or INSEE updates their API URL.

```env
INSEE_BASE_URL=https://api.insee.fr/api-sirene/3.11
```

### cache_duration

How long to cache OAuth tokens in seconds. The default is 82800 seconds (23 hours), which accounts for INSEE's typical token expiry time.

**Environment Variable:** `INSEE_CACHE_DURATION`

**Default:** `82800` (23 hours)

Reducing this value will cause more frequent token requests, while increasing it may result in invalid token errors if INSEE revokes your token. We recommend leaving this at the default unless you have a specific reason to adjust it.

```env
INSEE_CACHE_DURATION=82800
```

## Environment-Specific Configuration

You can use different credentials for different environments by using Laravel's environment files:

**.env.local** (Development)
```env
INSEE_CLIENT_SECRET=dev_secret_key
INSEE_CLIENT_ID=dev_client_id
```

**.env.production** (Production)
```env
INSEE_CLIENT_SECRET=prod_secret_key
INSEE_CLIENT_ID=prod_client_id
INSEE_CACHE_DURATION=82800
```

## Accessing Configuration

You can access configuration values in your code using Laravel's `config()` helper:

```php
$secret = config('insee.client_secret');
$baseUrl = config('insee.base_url');
$cacheDuration = config('insee.cache_duration');
```

## Troubleshooting

### "Missing INSEE_CLIENT_SECRET" Error

Ensure you've set the `INSEE_CLIENT_SECRET` environment variable in your `.env` file and have published the package configuration:

```bash
php artisan vendor:publish --tag=oi-laravel-insee-config
```

### API Connection Issues

Verify that:
1. Your credentials are correct
2. Your IP address is whitelisted (if applicable) with INSEE
3. The `INSEE_BASE_URL` is correct and accessible
4. You have internet connectivity to reach the INSEE API

### Token Caching Issues

If you're experiencing stale token errors, you can temporarily reduce the `INSEE_CACHE_DURATION`:

```env
INSEE_CACHE_DURATION=3600
```

This will force new tokens to be requested every hour. Remember to set it back to the default once the issue is resolved.
