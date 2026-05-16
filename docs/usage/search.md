---
title: Search
description: Search for companies and establishments
order: 4
---

# Search

The package provides two methods for searching the INSEE database:
- `searchCompanies()` - Search for companies (legal units)
- `searchEstablishments()` - Search for establishments

## Method Signatures

```php
public function searchCompanies(array $params): array
public function searchEstablishments(array $params): array
```

## Parameters

Both methods accept an associative array with the following parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | The search query (REQUIRED) |
| `nombre` | int | Number of results to return (default: 10, max: 1000) |
| `debut` | int | Offset/pagination cursor (default: 0) |

For detailed information about query syntax, see [Search Syntax](/docs/search-syntax/search-syntax).

## Search Companies

The `searchCompanies()` method searches for companies (legal units) by name, SIREN, activity, or other criteria.

### Basic Example

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$results = Insee::searchCompanies([
    'q' => 'Apple',
    'nombre' => 10
]);
```

### Response Structure

```php
{
    "header": {
        "statut": 200,
        "message": "OK",
        "nombre": 10,
        "total": 42,
        "debut": 0
    },
    "unitesLegales": [
        {
            "siren": "732829320",
            "denomination": "Apple France SARL",
            "activitePrincipaleUniteLegale": "6311Z",
            "etatAdministratifUniteLegale": "A",
            // ... more fields
        },
        // ... more results
    ]
}
```

### Working with Results

```php
$results = Insee::searchCompanies(['q' => 'Apple']);

echo "Found " . $results['header']['total'] . " companies";
echo "Showing " . count($results['unitesLegales']) . " results";

foreach ($results['unitesLegales'] as $company) {
    echo $company['denomination'];
    echo "Status: " . $company['etatAdministratifUniteLegale'];
}
```

### Example: Search with Filtering

```php
// Search for inactive companies
$results = Insee::searchCompanies([
    'q' => 'denomination:"Apple" AND etatAdministratifUniteLegale:F',
    'nombre' => 50
]);

foreach ($results['unitesLegales'] as $company) {
    echo $company['denomination'] . " (Closed)";
}
```

## Search Establishments

The `searchEstablishments()` method searches for establishments (physical locations) by name, SIRET, postal code, city, or other criteria.

### Basic Example

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$results = Insee::searchEstablishments([
    'q' => 'Apple Paris',
    'nombre' => 20
]);
```

### Response Structure

```php
{
    "header": {
        "statut": 200,
        "message": "OK",
        "nombre": 20,
        "total": 156,
        "debut": 0
    },
    "etablissements": [
        {
            "siret": "73282932000074",
            "siren": "732829320",
            "denominationUsuelleEtablissement": "Apple France",
            "codePostalEtablissement": "75008",
            "libelleCommuneEtablissement": "PARIS",
            "etatAdministratifEtablissement": "A",
            // ... more fields
        },
        // ... more results
    ]
}
```

### Working with Results

```php
$results = Insee::searchEstablishments(['q' => 'Apple', 'nombre' => 20]);

foreach ($results['etablissements'] as $etab) {
    echo $etab['denominationUsuelleEtablissement'];
    echo $etab['codePostalEtablissement'] . " " . $etab['libelleCommuneEtablissement'];
}
```

### Example: Search by Postal Code

```php
$results = Insee::searchEstablishments([
    'q' => 'codePostalEtablissement:75008',
    'nombre' => 100
]);

echo "Found " . $results['header']['total'] . " establishments in postal code 75008";
```

## Pagination

Both search methods support pagination using the `debut` (offset) parameter.

### Example: Get All Results with Pagination

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$allResults = [];
$offset = 0;
$pageSize = 100;

while (true) {
    $response = INSEE::searchCompanies([
        'q' => 'Apple',
        'nombre' => $pageSize,
        'debut' => $offset
    ]);

    $allResults = array_merge(
        $allResults,
        $response['unitesLegales'] ?? []
    );

    // Check if there are more results
    if ($response['header']['debut'] + $pageSize >= $response['header']['total']) {
        break;
    }

    $offset += $pageSize;
}

echo "Retrieved " . count($allResults) . " total results";
```

### Example: Paginated Controller

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;

class SearchController extends Controller
{
    public function __construct(private Client $insee) {}

    public function companies(string $query, int $page = 1)
    {
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;

        $response = $this->insee->searchCompanies([
            'q' => $query,
            'nombre' => $pageSize,
            'debut' => $offset
        ]);

        if ($response['header']['statut'] !== 200) {
            return redirect()->back()->withError('Search failed');
        }

        $total = $response['header']['total'];
        $lastPage = ceil($total / $pageSize);

        return view('search.companies', [
            'companies' => $response['unitesLegales'],
            'currentPage' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }
}
```

## Response Header Information

Both search methods return header information:

| Field | Type | Description |
|-------|------|-------------|
| `statut` | int | Status code (200 = success) |
| `message` | string | Status message |
| `nombre` | int | Number of results in this response |
| `total` | int | Total number of matching results |
| `debut` | int | Starting offset of this response |

### Using Header Information

```php
$results = INSEE::searchCompanies(['q' => 'Apple', 'nombre' => 10]);

$total = $results['header']['total'];
$returned = $results['header']['nombre'];
$nextPage = $results['header']['debut'] + $returned;

echo "Returned $returned of $total results";
echo "Next page starts at offset: $nextPage";
```

## Error Handling

```php
$results = INSEE::searchCompanies(['q' => 'Apple']);

if ($results['header']['statut'] !== 200) {
    echo "Search error: " . $results['header']['message'];
    exit;
}

if (empty($results['unitesLegales'])) {
    echo "No results found";
    exit;
}

// Process results
foreach ($results['unitesLegales'] as $company) {
    // ...
}
```

## Best Practices

1. **Always check the response status** before processing results
2. **Use pagination** for large result sets to avoid performance issues
3. **Limit results** with the `nombre` parameter to the minimum you need
4. **Use specific search syntax** to narrow down results and reduce API load
5. **Cache results** when appropriate to respect INSEE rate limits

## Tips

- The `nombre` parameter defaults to 10 but can be increased up to 1000
- Use the `total` field in the header to understand the scope of results
- Empty results return `statut: 200` but with an empty array and `total: 0`
- For complex searches, use the full search syntax described in [Search Syntax](/docs/search-syntax/search-syntax)
- Pagination starts at offset 0, not 1
