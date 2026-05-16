---
title: Find by SIREN
description: Look up a company by its SIREN identifier
order: 2
---

# Find by SIREN

The `findSiren()` method looks up a company (legal unit) by its 9-digit SIREN identifier.

## Method Signature

```php
public function findSiren(string $siren): array
```

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$siren` | string | A 9-digit SIREN identifier |

## Returns

An associative array containing:
- `header` - Response metadata
- `uniteLegale` - The company data
- `dirigeant` - (Auto-injected) Leadership info for natural persons

## Basic Example

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$company = Insee::findSiren('732829320');
```

## Full Response Example

```php
{
    "header": {
        "statut": 200,
        "message": "OK"
    },
    "uniteLegale": {
        "siren": "732829320",
        "denomination": "Apple France SARL",
        "categorieJuridiqueUniteLegale": "5699",
        "categorieJuridiqueUniteLegaleLabel": "Société à responsabilité limitée",
        "activitePrincipaleUniteLegale": "6311Z",
        "activitePrincipaleUniteLegaleLabel": "Traitement de données, hébergement et activités connexes",
        "etatAdministratifUniteLegale": "A",
        "dateCreationUniteLegale": "1988-11-14",
        "trancheEffectifsUniteLegale": "52",
        "trancheEffectifsUniteLegaleLabel": "500 à 999 salariés",
        "capitalSocial": 1000000,
        // ... more fields
    }
}
```

## Working with the Response

### Access Company Name

```php
$company = Insee::findSiren('732829320');
$name = $company['uniteLegale']['denomination'];
echo $name; // Output: Apple France SARL
```

### Check Company Status

```php
$company = Insee::findSiren('732829320');
$status = $company['uniteLegale']['etatAdministratifUniteLegale'];

if ($status === 'A') {
    echo 'Company is active';
} else if ($status === 'F') {
    echo 'Company is closed';
}
```

### Get Company Size

```php
$company = Insee::findSiren('732829320');
$sizeLabel = $company['uniteLegale']['trancheEffectifsUniteLegaleLabel'];
echo $sizeLabel; // Output: 500 à 999 salariés
```

### Get Business Activity

```php
$company = Insee::findSiren('732829320');
$activity = $company['uniteLegale']['activitePrincipaleUniteLegaleLabel'];
echo $activity; // Output: Traitement de données, hébergement et activités connexes
```

## Error Handling

If the SIREN is not found or invalid, the response will have a non-200 status:

```php
$company = Insee::findSiren('999999999');

if ($company['header']['statut'] === 404) {
    echo 'Company not found';
} else if ($company['header']['statut'] === 400) {
    echo 'Invalid SIREN format';
}
```

## In a Controller

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

        $company = $response['uniteLegale'];

        return view('company.show', [
            'name' => $company['denomination'],
            'activity' => $company['activitePrincipaleUniteLegaleLabel'],
            'status' => $company['etatAdministratifUniteLegale'],
            'employees' => $company['trancheEffectifsUniteLegaleLabel'],
        ]);
    }
}
```

## Common SIREN Fields

| Field | Description |
|-------|-------------|
| `siren` | The 9-digit SIREN identifier |
| `denomination` | The official company name |
| `categorieJuridiqueUniteLegale` | Legal entity type code |
| `categorieJuridiqueUniteLegaleLabel` | Human-readable legal entity type |
| `activitePrincipaleUniteLegale` | NAF code (business activity code) |
| `activitePrincipaleUniteLegaleLabel` | Human-readable business activity |
| `etatAdministratifUniteLegale` | Administrative status (A=active, F=closed) |
| `dateCreationUniteLegale` | Company creation date |
| `trancheEffectifsUniteLegale` | Employee range code |
| `trancheEffectifsUniteLegaleLabel` | Human-readable employee range |
| `capitalSocial` | Share capital amount (in EUR) |

## Automatic Dirigeant Injection

For natural persons (entrepreneurs, self-employed individuals), the package automatically injects leadership information:

```php
$company = Insee::findSiren('123456789'); // Natural person

if (isset($company['dirigeant'])) {
    echo $company['dirigeant']['nom']; // Last name
    echo $company['dirigeant']['prenom']; // First name
}
```

See [Dirigeant Injection](/docs/response-format/dirigeant) for more details.

## Tips

- SIREN numbers are always 9 digits. The package may accept strings with leading zeros.
- All responses, whether successful or not, contain a `header` with `statut` and `message` fields.
- Use the `statut` field to determine if the lookup was successful (200 = success).
- The INSEE API returns comprehensive data; not all fields may be populated for every company.
