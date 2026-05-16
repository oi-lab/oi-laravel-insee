---
title: Overview
description: Overview of available methods and usage patterns
order: 1
---

# Usage Overview

The OI Laravel INSEE package provides 5 core methods for interacting with the INSEE SIRENE API. You can access these methods using either the Facade pattern or Dependency Injection.

## Available Methods

| Method | Purpose |
|--------|---------|
| `findSiren(string $siren): array` | Find a company by its 9-digit SIREN |
| `findSiret(string $siret): array` | Find an establishment by its 14-digit SIRET |
| `searchCompanies(array $params): array` | Search for companies using query parameters |
| `searchEstablishments(array $params): array` | Search for establishments using query parameters |
| `getApiStatus(): array` | Check if the INSEE API is operational |

## Usage Pattern 1: Facade

The Facade pattern is ideal for simple, one-off operations or when you don't need type hints.

```php
use OiLab\OiLaravelInsee\Facades\Insee;

// Find a company by SIREN
$company = Insee::findSiren('732829320');

// Find an establishment by SIRET
$establishment = Insee::findSiret('73282932000074');

// Search for companies
$results = Insee::searchCompanies(['q' => 'Apple']);

// Check API status
$status = Insee::getApiStatus();
```

## Usage Pattern 2: Dependency Injection

Dependency Injection is recommended for controllers, services, and any code that benefits from testing and type safety.

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;

class CompanyController extends Controller
{
    public function __construct(private Client $insee) {}

    public function show(string $siren)
    {
        $company = $this->insee->findSiren($siren);
        
        return view('company.show', ['company' => $company]);
    }

    public function search(string $query)
    {
        $results = $this->insee->searchCompanies(['q' => $query]);
        
        return view('company.search-results', ['results' => $results]);
    }
}
```

## Basic Examples

### Find a Company

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$company = Insee::findSiren('732829320');

echo $company['uniteLegale']['denomination'];
// Output: Apple France SARL
```

### Search for Companies

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$results = Insee::searchCompanies([
    'q' => 'Apple',
    'nombre' => 10
]);

foreach ($results['unitesLegales'] as $company) {
    echo $company['denomination'];
}
```

### Check API Status

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$status = Insee::getApiStatus();

if ($status['header']['statut'] === 200) {
    echo 'API is operational';
}
```

## Response Structure

All methods return an associative array containing:

- **header** - Metadata about the response (status, number of results, etc.)
- **uniteLegale** or **unitesLegales** - Company data (single or multiple)
- **etablissement** or **etablissements** - Establishment data (single or multiple)
- **dirigeant** - (Automatically injected) Leadership information for natural persons

For detailed information about response structures, see the [Response Format](/docs/response-format/dirigeant) section.

## Error Handling

All responses include a `header` with a `statut` field. A successful response has `statut: 200`. Other status codes indicate errors:

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$company = Insee::findSiren('invalid_siren');

if ($company['header']['statut'] !== 200) {
    // Handle error
    $message = $company['header']['message'] ?? 'Unknown error';
    echo "Error: $message";
}
```

## Next Steps

Explore the specific methods:
- [Find by SIREN](/docs/usage/find-by-siren)
- [Find by SIRET](/docs/usage/find-by-siret)
- [Search](/docs/usage/search)
- [API Status](/docs/usage/api-status)

Or dive into [Search Syntax](/docs/search-syntax/search-syntax) to learn how to write powerful search queries.
