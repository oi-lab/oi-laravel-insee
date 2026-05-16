---
title: API Status
description: Check if the INSEE API is operational
order: 5
---

# API Status

The `getApiStatus()` method checks if the INSEE SIRENE API is currently operational and accessible.

## Method Signature

```php
public function getApiStatus(): array
```

## Returns

An associative array containing:
- `header` - Response metadata with status information

## Basic Example

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$status = INSEE::getApiStatus();
```

## Response Structure

```php
{
    "header": {
        "statut": 200,
        "message": "OK"
    }
}
```

## Checking API Status

### Simple Status Check

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$status = INSEE::getApiStatus();

if ($status['header']['statut'] === 200) {
    echo 'INSEE API is operational';
} else {
    echo 'INSEE API is not responding correctly';
}
```

### In Application Bootstrap

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelInsee\Facades\INSEE;

class CheckInseeApiCommand extends Command
{
    protected $signature = 'insee:check-api';
    protected $description = 'Check if the INSEE API is operational';

    public function handle(): int
    {
        $this->info('Checking INSEE API status...');

        $status = INSEE::getApiStatus();

        if ($status['header']['statut'] === 200) {
            $this->info('✓ INSEE API is operational');
            return 0;
        } else {
            $this->error('✗ INSEE API is not responding');
            $this->error('Message: ' . ($status['header']['message'] ?? 'Unknown error'));
            return 1;
        }
    }
}
```

## Use Cases

### Health Check Endpoint

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;

class HealthController extends Controller
{
    public function __construct(private Client $insee) {}

    public function check()
    {
        $inseeStatus = $this->insee->getApiStatus();

        return response()->json([
            'database' => true,
            'insee_api' => $inseeStatus['header']['statut'] === 200,
            'cache' => true,
        ]);
    }
}
```

### Graceful Degradation

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$status = INSEE::getApiStatus();

if ($status['header']['statut'] !== 200) {
    // Fall back to cached/offline data
    return view('company.show-cached', [
        'company' => cache('company.' . $id),
        'message' => 'INSEE API is temporarily unavailable. Showing cached data.'
    ]);
}

// Use live data
$company = INSEE::findSiren($siren);
// ...
```

### Scheduled Health Check

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelInsee\Facades\INSEE;

class MonitorInseeApiCommand extends Command
{
    protected $signature = 'insee:monitor';
    protected $description = 'Monitor INSEE API and log status';

    public function handle(): int
    {
        $status = INSEE::getApiStatus();

        if ($status['header']['statut'] !== 200) {
            \Log::warning('INSEE API is unavailable', $status);
            \Notification::route('mail', config('app.admin_email'))
                ->notify(new InseeApiDownNotification());
        }

        return 0;
    }
}
```

## Status Codes

| Code | Meaning |
|------|---------|
| 200 | API is operational |
| 400 | Bad request |
| 401 | Unauthorized (invalid credentials) |
| 429 | Rate limit exceeded |
| 500 | Server error |
| 503 | Service unavailable |

## Performance Considerations

- This is a lightweight request and can be used for health checks
- Consider caching the result for a few minutes to avoid unnecessary requests
- Use it in scheduled jobs rather than on every page load if possible

### Cached Health Check

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

if (!cache()->has('insee_api_status')) {
    $status = INSEE::getApiStatus();
    cache(['insee_api_status' => $status], now()->addMinutes(5));
} else {
    $status = cache('insee_api_status');
}

if ($status['header']['statut'] === 200) {
    echo 'INSEE API is operational';
}
```

## Tips

- Use this endpoint during application startup or in health check routes
- Combine with other system health checks to monitor overall application status
- Log API status changes to track availability patterns
- Consider alerting administrators if the API becomes unavailable
