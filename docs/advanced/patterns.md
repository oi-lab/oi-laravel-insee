---
title: Usage Patterns
description: Recommended patterns and best practices
order: 2
---

# Usage Patterns

This guide covers three recommended patterns for using the OI Laravel INSEE package in your application.

## Pattern 1: Facade (Simple Scripts)

Use the Facade for quick, one-off operations in simple contexts:

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$company = INSEE::findSiren('732829320');
echo $company['uniteLegale']['denomination'];
```

### When to Use

- Simple controllers or routes
- Quick lookups without complex logic
- Testing or prototyping
- Scripts or commands

### Example: Simple Route

```php
Route::get('/company/{siren}', function (string $siren) {
    $response = INSEE::findSiren($siren);
    
    if ($response['header']['statut'] !== 200) {
        return response('Not found', 404);
    }

    return $response['uniteLegale'];
})->name('company.show');
```

### Example: Artisan Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelInsee\Facades\INSEE;

class LookupCompanyCommand extends Command
{
    protected $signature = 'company:lookup {siren}';
    protected $description = 'Look up a company by SIREN';

    public function handle(): int
    {
        $siren = $this->argument('siren');
        $response = INSEE::findSiren($siren);

        if ($response['header']['statut'] !== 200) {
            $this->error('Company not found');
            return 1;
        }

        $company = $response['uniteLegale'];
        
        $this->table(
            ['Field', 'Value'],
            [
                ['SIREN', $company['siren']],
                ['Name', $company['denomination']],
                ['Status', $company['etatAdministratifUniteLegale']],
            ]
        );

        return 0;
    }
}
```

## Pattern 2: Dependency Injection (Recommended)

Inject the `Client` class for testable, type-hinted code:

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;

class CompanyController extends Controller
{
    public function __construct(private Client $insee) {}

    public function show(string $siren)
    {
        $response = $this->insee->findSiren($siren);
        
        if ($response['header']['statut'] !== 200) {
            return redirect()->back()->withError('Company not found');
        }

        return view('company.show', ['company' => $response['uniteLegale']]);
    }
}
```

### When to Use

- Controllers (most common)
- Services and classes
- Code that needs testing
- Production applications
- Complex business logic

### Example: Service Class

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;
use App\Models\Company;

class CompanyImportService
{
    public function __construct(private Client $insee) {}

    public function importCompany(string $siren): ?Company
    {
        $response = $this->insee->findSiren($siren);

        if ($response['header']['statut'] !== 200) {
            throw new CompanyNotFoundException("SIREN: $siren");
        }

        $data = $response['uniteLegale'];

        return Company::updateOrCreate(
            ['siren' => $data['siren']],
            [
                'name' => $data['denomination'],
                'status' => $data['etatAdministratifUniteLegale'],
                'activity' => $data['activitePrincipaleUniteLegale'],
                'employees' => $data['trancheEffectifsUniteLegale'],
                'director_name' => $this->extractDirector($response),
            ]
        );
    }

    private function extractDirector(array $response): ?string
    {
        if (!isset($response['dirigeant'])) {
            return null;
        }

        $d = $response['dirigeant'];
        return trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''));
    }
}
```

### Example: Resource Controller

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private Client $insee) {}

    public function show(string $siren): View
    {
        $response = $this->insee->findSiren($siren);

        if ($response['header']['statut'] !== 200) {
            abort(404, 'Company not found');
        }

        return view('company.show', ['company' => $response['uniteLegale']]);
    }

    public function search(Request $request): View
    {
        $query = $request->input('q');
        
        if (!$query) {
            return view('company.search', ['results' => []]);
        }

        $response = $this->insee->searchCompanies([
            'q' => $query,
            'nombre' => 20,
        ]);

        $companies = [];
        if ($response['header']['statut'] === 200) {
            $companies = $response['unitesLegales'] ?? [];
        }

        return view('company.search', ['results' => $companies]);
    }
}
```

## Pattern 3: App Helper

Use the `app()` helper for one-off access without dependency injection:

```php
$company = app(Client::class)->findSiren('732829320');
```

### When to Use

- Closures or callbacks
- Helper functions
- When you can't use dependency injection

### Example: Route Model Binding

```php
Route::bind('company', function (string $siren) {
    $response = app(\OiLab\OiLaravelInsee\Client::class)->findSiren($siren);
    
    if ($response['header']['statut'] !== 200) {
        abort(404);
    }

    return $response['uniteLegale'];
});

Route::get('/company/{company}', function ($company) {
    return view('company.show', ['company' => $company]);
});
```

## Batch Processing Pattern

Look up multiple companies efficiently with error recovery:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;

class BatchCompanyImporter
{
    public function __construct(private Client $insee) {}

    public function importSirens(array $sirens): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($sirens as $siren) {
            try {
                $response = $this->insee->findSiren($siren);

                if ($response['header']['statut'] === 200) {
                    $results['success'][] = $response['uniteLegale'];
                } else {
                    $results['failed'][$siren] = 'Not found';
                }
            } catch (\Exception $e) {
                $results['failed'][$siren] = $e->getMessage();
            }

            // Be respectful of INSEE rate limits
            usleep(100000); // 100ms delay between requests
        }

        return $results;
    }
}
```

### Usage in a Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BatchCompanyImporter;

class ImportCompaniesCommand extends Command
{
    public function __construct(private BatchCompanyImporter $importer) {}

    public function handle(): int
    {
        $sirens = $this->argument('sirens');
        
        $this->info('Importing ' . count($sirens) . ' companies...');

        $results = $this->importer->importSirens($sirens);

        $this->info('Successfully imported: ' . count($results['success']));
        $this->error('Failed: ' . count($results['failed']));

        foreach ($results['failed'] as $siren => $reason) {
            $this->line("  - $siren: $reason");
        }

        return count($results['failed']) > 0 ? 1 : 0;
    }
}
```

## Caching Pattern

Cache successful results to minimize API calls:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;
use Illuminate\Support\Facades\Cache;

class CachedInseeService
{
    public function __construct(private Client $insee) {}

    public function findCompany(string $siren, int $cacheTtl = 86400): array
    {
        $cacheKey = "insee:company:$siren";

        return Cache::remember($cacheKey, $cacheTtl, function () use ($siren) {
            return $this->insee->findSiren($siren);
        });
    }

    public function searchCompanies(
        string $query,
        int $limit = 20,
        int $cacheTtl = 3600
    ): array {
        $cacheKey = "insee:search:" . md5($query . $limit);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($query, $limit) {
            return $this->insee->searchCompanies([
                'q' => $query,
                'nombre' => $limit,
            ]);
        });
    }

    public function invalidateCompanyCache(string $siren): void
    {
        Cache::forget("insee:company:$siren");
    }
}
```

## Testing Pattern

Test your code that uses INSEE without hitting the real API:

```php
<?php

use OiLab\OiLaravelInsee\Client;

it('displays company information', function () {
    $mockResponse = [
        'header' => ['statut' => 200, 'message' => 'OK'],
        'uniteLegale' => [
            'siren' => '732829320',
            'denomination' => 'Apple France SARL',
            'etatAdministratifUniteLegale' => 'A',
        ],
    ];

    // Mock the INSEE client
    $this->mock(Client::class, function ($mock) use ($mockResponse) {
        $mock->shouldReceive('findSiren')
            ->with('732829320')
            ->andReturn($mockResponse);
    });

    // Your test
    $response = $this->get('/company/732829320');

    $response->assertSee('Apple France SARL');
});
```

## Error Recovery Pattern

Implement robust error handling with fallbacks:

```php
<?php

namespace App\Services;

use OiLab\OiLaravelInsee\Client;

class RobustInseeService
{
    public function __construct(private Client $insee) {}

    public function findCompanyWithFallback(string $siren): array
    {
        // Try live API
        $response = $this->insee->findSiren($siren);

        if ($response['header']['statut'] === 200) {
            return $response;
        }

        // Try cache
        $cached = cache("company:$siren:full");
        if ($cached) {
            return [
                ...$cached,
                'from_cache' => true,
                'header' => [
                    ...$cached['header'],
                    'message' => 'Served from cache',
                ],
            ];
        }

        // Return error
        return $response;
    }

    public function findCompanyWithRetry(string $siren, int $maxRetries = 3): array
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = $this->insee->findSiren($siren);

            if ($response['header']['statut'] === 200) {
                return $response;
            }

            if ($attempt < $maxRetries) {
                sleep(1 * $attempt); // Exponential backoff
            }
        }

        return $response;
    }
}
```

## Queue Pattern

Process lookups asynchronously:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OiLab\OiLaravelInsee\Client;
use App\Models\Company;

class ImportCompanyDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $siren) {}

    public function handle(Client $insee): void
    {
        $response = $insee->findSiren($this->siren);

        if ($response['header']['statut'] !== 200) {
            $this->fail(new \Exception('Company not found'));
            return;
        }

        $data = $response['uniteLegale'];

        Company::updateOrCreate(
            ['siren' => $data['siren']],
            [
                'name' => $data['denomination'],
                'status' => $data['etatAdministratifUniteLegale'],
                'activity' => $data['activitePrincipaleUniteLegale'],
            ]
        );
    }
}
```

## Rate Limit Awareness

Be mindful of INSEE rate limits:

```php
// Add delays between requests
foreach ($sirens as $siren) {
    $response = $insee->findSiren($siren);
    usleep(250000); // 250ms between requests
}

// Or cache aggressively
cache()->rememberForever("company:$siren", function () use ($insee, $siren) {
    return $insee->findSiren($siren);
});

// Or batch requests
$results = $insee->searchCompanies([
    'q' => 'search terms',
    'nombre' => 100, // Get more results per request
]);
```

## Tips

- Prefer dependency injection for production code
- Use the Facade for quick scripts and commands
- Cache results to minimize API calls
- Implement retry logic for transient failures
- Be respectful of INSEE rate limits
- Always handle errors gracefully
- Test your code with mocks
- Log all API interactions for debugging
