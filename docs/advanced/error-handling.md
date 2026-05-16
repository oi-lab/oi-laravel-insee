---
title: Error Handling
description: Handle API errors and exceptions properly
order: 1
---

# Error Handling

The OI Laravel INSEE package returns all API responses in a consistent format with status codes. Proper error handling ensures your application gracefully manages API issues.

## Response Status Codes

All method responses include a `header` with a `statut` field:

| Status | Meaning | Common Cause |
|--------|---------|--------------|
| 200 | Success | Valid request, data found |
| 400 | Bad Request | Invalid parameters or malformed query |
| 401 | Unauthorized | Invalid API credentials |
| 404 | Not Found | SIREN/SIRET doesn't exist |
| 429 | Rate Limited | Too many requests (INSEE rate limit) |
| 500 | Server Error | INSEE API server error |
| 503 | Service Unavailable | INSEE API is down |

## Checking Response Status

Always check the response status before processing data:

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$response = INSEE::findSiren('123456789');

if ($response['header']['statut'] === 200) {
    // Process data
    $company = $response['uniteLegale'];
} else {
    // Handle error
    $message = $response['header']['message'] ?? 'Unknown error';
    log_error("INSEE API error: $message");
}
```

## Common Error Scenarios

### Not Found (404)

Occurs when a SIREN or SIRET doesn't exist:

```php
$response = INSEE::findSiren('999999999');

if ($response['header']['statut'] === 404) {
    return redirect()->back()->withError('Company not found');
}
```

### Bad Request (400)

Occurs when the query syntax is invalid:

```php
$response = INSEE::searchCompanies([
    'q' => 'invalid::syntax::'
]);

if ($response['header']['statut'] === 400) {
    return redirect()->back()->withError('Invalid search query');
}
```

### Unauthorized (401)

Occurs when API credentials are missing or invalid:

```php
$response = INSEE::findSiren('123456789');

if ($response['header']['statut'] === 401) {
    Log::error('INSEE API: Invalid credentials');
    // This should be fixed in configuration, not at runtime
}
```

### Rate Limited (429)

Occurs when you exceed INSEE's request limits:

```php
$response = INSEE::searchCompanies(['q' => 'Apple']);

if ($response['header']['statut'] === 429) {
    Log::warning('INSEE API: Rate limit exceeded');
    return redirect()->back()->withError(
        'Too many requests. Please try again later.'
    );
}
```

### Server Error (500+)

Occurs when INSEE API is having issues:

```php
$response = INSEE::findSiren('123456789');

if ($response['header']['statut'] >= 500) {
    Log::error('INSEE API server error: ' . $response['header']['statut']);
    return redirect()->back()->withError(
        'INSEE service is temporarily unavailable. Please try again later.'
    );
}
```

## Helper Function Pattern

Create a helper function to standardize error handling:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Facades\INSEE;

class InseeService
{
    public static function handleResponse(array $response): array
    {
        if ($response['header']['statut'] !== 200) {
            return [
                'success' => false,
                'error' => self::getErrorMessage($response),
                'status' => $response['header']['statut'],
            ];
        }

        return [
            'success' => true,
            'data' => $response,
        ];
    }

    private static function getErrorMessage(array $response): string
    {
        $statut = $response['header']['statut'];
        $message = $response['header']['message'] ?? 'Unknown error';

        return match ($statut) {
            400 => 'Invalid request parameters',
            401 => 'API credentials are invalid',
            404 => 'Company or establishment not found',
            429 => 'Too many requests. Please try again later.',
            500, 502, 503, 504 => 'INSEE service is temporarily unavailable',
            default => $message,
        };
    }
}
```

## In a Controller

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;
use App\Services\InseeService;

class CompanyController extends Controller
{
    public function __construct(private Client $insee) {}

    public function lookup(string $siren)
    {
        $response = $this->insee->findSiren($siren);
        $result = InseeService::handleResponse($response);

        if (!$result['success']) {
            return back()->withError($result['error']);
        }

        return view('company.show', [
            'company' => $result['data']['uniteLegale']
        ]);
    }

    public function search(string $query)
    {
        $response = $this->insee->searchCompanies([
            'q' => $query,
            'nombre' => 20
        ]);

        $result = InseeService::handleResponse($response);

        if (!$result['success']) {
            return back()->withError($result['error']);
        }

        return view('company.search', [
            'companies' => $result['data']['unitesLegales']
        ]);
    }
}
```

## Graceful Degradation

When the INSEE API is unavailable, fall back to cached data:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;

class CompanyLookupService
{
    public function __construct(private Client $insee) {}

    public function findCompany(string $siren): array
    {
        // Try INSEE API first
        $response = $this->insee->findSiren($siren);

        if ($response['header']['statut'] === 200) {
            // Cache the result for 24 hours
            cache([
                "company.$siren" => $response
            ], now()->addDay());

            return $response;
        }

        // Fall back to cache
        $cached = cache("company.$siren");
        if ($cached) {
            return [
                'header' => [
                    'statut' => 200,
                    'message' => 'OK (cached)',
                ],
                'cached' => true,
                ...$cached
            ];
        }

        // No data available
        return $response;
    }
}
```

## Retry Pattern

For transient failures (429, 5xx), implement exponential backoff:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;

class InseeRetryService
{
    private const MAX_RETRIES = 3;
    private const BASE_DELAY = 1; // seconds

    public function __construct(private Client $insee) {}

    public function findSirenWithRetry(string $siret): array
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $response = $this->insee->findSiret($siret);

            // Success
            if ($response['header']['statut'] === 200) {
                return $response;
            }

            // Not found (don't retry)
            if ($response['header']['statut'] === 404) {
                return $response;
            }

            // Client errors (don't retry)
            if ($response['header']['statut'] < 500 && 
                $response['header']['statut'] !== 429) {
                return $response;
            }

            // Retryable error (429, 5xx)
            if ($attempt < self::MAX_RETRIES - 1) {
                $delay = self::BASE_DELAY * (2 ** $attempt);
                sleep($delay);
            }
        }

        return $response; // Return last response after max retries
    }
}
```

## Logging Best Practices

Log errors for monitoring and debugging:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InseeLogger
{
    public static function logApiError(array $response, string $context): void
    {
        $statut = $response['header']['statut'];
        $message = $response['header']['message'] ?? 'Unknown';

        Log::warning("INSEE API Error [$statut]", [
            'context' => $context,
            'status' => $statut,
            'message' => $message,
            'timestamp' => now(),
        ]);

        // Alert on critical failures
        if ($statut >= 500) {
            Log::alert('INSEE API Server Error', [
                'status' => $statut,
                'context' => $context,
            ]);
        }
    }
}
```

## Validation Before API Call

Validate inputs before making API requests to avoid unnecessary errors:

```php
use Illuminate\Support\Facades\Validator;

$validated = Validator::validate(request()->all(), [
    'siren' => 'required|digits:9|numeric',
    'siret' => 'required|digits:14|numeric',
]);

if (!$validated) {
    return back()->withErrors($validated->errors());
}

$response = $this->insee->findSiren($validated['siren']);
```

## HTML Error Templates

Create user-friendly error pages:

```blade
@if($error)
    <div class="alert alert-danger">
        <strong>Error:</strong> {{ $error }}
        <p class="text-sm mt-2">
            @switch($errorCode)
                @case(404)
                    The company or establishment was not found.
                    @break
                @case(429)
                    Too many requests. Please wait a moment and try again.
                    @break
                @case(500)
                @case(503)
                    The INSEE service is temporarily unavailable. Please try again later.
                    @break
                @default
                    An error occurred while looking up company information.
            @endswitch
        </p>
    </div>
@endif
```

## Monitoring and Alerts

Monitor API health and alert on issues:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelInsee\Facades\INSEE;

class MonitorInseeApiCommand extends Command
{
    protected $signature = 'insee:monitor';

    public function handle(): int
    {
        $status = INSEE::getApiStatus();

        if ($status['header']['statut'] !== 200) {
            $this->alert('INSEE API is unavailable!');
            Notification::route('mail', config('app.admin_email'))
                ->notify(new InseeDownNotification());

            return 1;
        }

        $this->info('INSEE API is operational');
        return 0;
    }
}
```

## Tips

- Always check the `statut` field; don't assume success
- Log all API errors for monitoring and debugging
- Use appropriate HTTP status codes in your responses
- Implement retry logic for transient failures
- Cache successful responses when appropriate
- Validate input data before making API calls
- Provide user-friendly error messages
- Consider graceful degradation when API is unavailable
