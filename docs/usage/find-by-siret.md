---
title: Find by SIRET
description: Look up an establishment by its SIRET identifier
order: 3
---

# Find by SIRET

The `findSiret()` method looks up an establishment by its 14-digit SIRET identifier.

## Method Signature

```php
public function findSiret(string $siret): array
```

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$siret` | string | A 14-digit SIRET identifier |

## Returns

An associative array containing:
- `header` - Response metadata
- `etablissement` - The establishment data
- `uniteLegale` - The parent company data (embedded)
- `dirigeant` - (Auto-injected) Leadership info for natural persons

## Basic Example

```php
use OiLab\OiLaravelInsee\Facades\Insee;

$establishment = Insee::findSiret('73282932000074');
```

## Full Response Example

```php
{
    "header": {
        "statut": 200,
        "message": "OK"
    },
    "etablissement": {
        "siret": "73282932000074",
        "siren": "732829320",
        "dateCreationEtablissement": "2011-07-18",
        "denominationUsuelleEtablissement": "Apple France",
        "etatAdministratifEtablissement": "A",
        "enseigne1Etablissement": "APPLE",
        "typeVoieEtablissement": "Avenue",
        "voieEtablissement": "de Friedland",
        "codePostalEtablissement": "75008",
        "libelleCommuneEtablissement": "PARIS",
        "libelleCommuneEtrangerEtablissement": null,
        "distributionSpecialeEtablissement": null,
        "indiceRepetitionEtablissement": "00088",
        "codeCommuneEtablissement": "75056",
        "numeroVoieEtablissement": "1",
        "activitePrincipaleEtablissement": "4791B",
        "activitePrincipaleEtablissementLabel": "Vente à distance sur catalogue général",
        "nomenclatureActivitePrincipaleEtablissement": "NAF",
        // ... more fields
    },
    "uniteLegale": {
        "siren": "732829320",
        "denomination": "Apple France SARL",
        "etatAdministratifUniteLegale": "A",
        // ... more company fields
    }
}
```

## Working with the Response

### Access Establishment Information

```php
$establishment = Insee::findSiret('73282932000074');
$siret = $establishment['etablissement']['siret'];
$name = $establishment['etablissement']['denominationUsuelleEtablissement'];
echo "$name ($siret)";
```

### Get Address Details

```php
$establishment = Insee::findSiret('73282932000074');
$etab = $establishment['etablissement'];

$address = sprintf(
    "%s %s %s, %s %s",
    $etab['numeroVoieEtablissement'],
    $etab['typeVoieEtablissement'],
    $etab['voieEtablissement'],
    $etab['codePostalEtablissement'],
    $etab['libelleCommuneEtablissement']
);

echo $address;
// Output: 1 Avenue de Friedland, 75008 PARIS
```

### Check Establishment Status

```php
$establishment = Insee::findSiret('73282932000074');
$status = $establishment['etablissement']['etatAdministratifEtablissement'];

if ($status === 'A') {
    echo 'Establishment is active';
} else if ($status === 'F') {
    echo 'Establishment is closed';
}
```

### Get Parent Company Information

```php
$establishment = Insee::findSiret('73282932000074');
$company = $establishment['uniteLegale'];
echo $company['denomination'];
// Output: Apple France SARL
```

## Error Handling

If the SIRET is not found or invalid, the response will have a non-200 status:

```php
$establishment = Insee::findSiret('99999999999999');

if ($establishment['header']['statut'] === 404) {
    echo 'Establishment not found';
} else if ($establishment['header']['statut'] === 400) {
    echo 'Invalid SIRET format';
}
```

## In a Controller

```php
<?php

namespace App\Http\Controllers;

use OiLab\OiLaravelInsee\Client;

class EstablishmentController extends Controller
{
    public function __construct(private Client $insee) {}

    public function show(string $siret)
    {
        $response = $this->insee->findSiret($siret);

        if ($response['header']['statut'] !== 200) {
            return redirect()->back()->withError('Establishment not found');
        }

        $etab = $response['etablissement'];
        $company = $response['uniteLegale'];

        return view('establishment.show', [
            'name' => $etab['denominationUsuelleEtablissement'],
            'company' => $company['denomination'],
            'status' => $etab['etatAdministratifEtablissement'],
            'address' => sprintf(
                "%s %s %s, %s %s",
                $etab['numeroVoieEtablissement'],
                $etab['typeVoieEtablissement'],
                $etab['voieEtablissement'],
                $etab['codePostalEtablissement'],
                $etab['libelleCommuneEtablissement']
            ),
        ]);
    }
}
```

## Common Establishment Fields

| Field | Description |
|-------|-------------|
| `siret` | The 14-digit SIRET identifier |
| `siren` | The 9-digit SIREN of the parent company |
| `denominationUsuelleEtablissement` | The establishment's trading name |
| `etatAdministratifEtablissement` | Status (A=active, F=closed) |
| `numeroVoieEtablissement` | Street number |
| `typeVoieEtablissement` | Street type (Avenue, Rue, Boulevard, etc.) |
| `voieEtablissement` | Street name |
| `codePostalEtablissement` | Postal code |
| `libelleCommuneEtablissement` | City name |
| `activitePrincipaleEtablissement` | NAF code (business activity) |
| `activitePrincipaleEtablissementLabel` | Human-readable business activity |
| `dateCreationEtablissement` | Establishment creation date |
| `enseigne1Etablissement` | Store brand name (if applicable) |

## Relationship to SIREN

Every SIRET contains a SIREN:
- The first 9 digits of a SIRET are the parent company's SIREN
- The digits 10-12 are an internal sequence number
- The last digit is a check digit

```php
$siret = '73282932000074';
$siren = substr($siret, 0, 9); // '732829320'
```

The response includes both the establishment data and the parent company data via `uniteLegale`.

## Automatic Dirigeant Injection

For natural persons (entrepreneurs operating an establishment), the package automatically injects leadership information:

```php
$establishment = Insee::findSiret('12345678901234'); // Natural person's establishment

if (isset($establishment['dirigeant'])) {
    echo $establishment['dirigeant']['nom']; // Last name
    echo $establishment['dirigeant']['prenom']; // First name
}
```

See [Dirigeant Injection](/docs/response-format/dirigeant) for more details.

## Tips

- SIRET numbers are always 14 digits (9-digit SIREN + 5 more digits)
- Each physical location of a company has its own SIRET
- The response always includes both the establishment and its parent company data
- Use the establishment's `etatAdministratifEtablissement` to check if it's currently operational
