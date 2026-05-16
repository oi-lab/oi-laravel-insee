---
title: Dirigeant Injection
description: Automatic leadership information extraction for natural persons
order: 1
---

# Dirigeant Injection

One of the package's unique features is the automatic extraction and injection of "dirigeant" (leadership) information for natural persons. This saves you from manually parsing complex response structures.

## What is a Dirigeant?

"Dirigeant" is French for "director" or "manager." In the INSEE database, it refers to the natural person responsible for a company or establishment—typically the owner, entrepreneur, or self-employed individual.

The package automatically extracts this information and injects it into responses when available.

## When Dirigeant is Injected

Dirigeant information is automatically injected for:

- **Individual entrepreneurs** (entreprise individuelle)
- **Micro-entrepreneurs** (auto-entrepreneurs)
- **Self-employed professionals** (artisans, consultants)
- Any entity registered as a **natural person** (personne physique)

Dirigeant is NOT injected for:

- Legal entities (SARL, EIRL, SAS, SA, etc.)
- Cooperatives
- Associations
- Government organizations
- Other organizational structures

## Extracted Dirigeant Fields

When available, the package injects a `dirigeant` key containing:

| Field | Description |
|-------|-------------|
| `nom` | Last name |
| `nomUsage` | Surname (maiden name, if applicable) |
| `prenom` | First name |
| `sexe` | Gender (M/F) |

## Examples by Method

### findSiren() with Dirigeant

```php
use OiLab\OiLaravelInsee\Facades\INSEE;

$entrepreneur = INSEE::findSiren('123456789'); // Natural person

// The response includes automatically injected dirigeant
if (isset($entrepreneur['dirigeant'])) {
    echo "Owner: " . $entrepreneur['dirigeant']['prenom'] . " " . 
         $entrepreneur['dirigeant']['nom'];
    // Output: Owner: Jean Dupont
}
```

### findSiret() with Dirigeant

```php
$establishment = INSEE::findSiret('12345678901234');

// If the establishment belongs to a natural person
if (isset($establishment['dirigeant'])) {
    echo "Contact: " . $establishment['dirigeant']['prenom'] . " " . 
         $establishment['dirigeant']['nom'];
}
```

### searchCompanies() with Dirigeant

```php
$results = INSEE::searchCompanies(['q' => 'Jean Dupont']);

foreach ($results['unitesLegales'] as $company) {
    // Each result may have dirigeant injected if it's a natural person
    if (isset($company['dirigeant'])) {
        echo $company['dirigeant']['prenom'] . " " . 
             $company['dirigeant']['nom'] . " - " . 
             $company['denomination'];
    }
}
```

### searchEstablishments() with Dirigeant

```php
$results = INSEE::searchEstablishments(['q' => 'Paris']);

foreach ($results['etablissements'] as $etab) {
    // Dirigeant automatically injected for natural persons
    if (isset($etab['dirigeant'])) {
        echo "Manager: " . $etab['dirigeant']['prenom'] . " " . 
             $etab['dirigeant']['nom'];
    }
}
```

## Working with Dirigeant Data

### Safe Access Pattern

Always check if `dirigeant` exists before accessing:

```php
$company = INSEE::findSiren('123456789');

if (isset($company['dirigeant'])) {
    $name = sprintf(
        "%s %s",
        $company['dirigeant']['prenom'] ?? '',
        $company['dirigeant']['nom'] ?? ''
    );
    echo trim($name);
} else {
    // Not a natural person
    echo "This is a legal entity";
}
```

### Building Display Names

```php
function getDirectorName($response): string
{
    if (!isset($response['dirigeant'])) {
        return 'N/A';
    }

    $d = $response['dirigeant'];
    
    $parts = array_filter([
        $d['prenom'] ?? null,
        $d['nomUsage'] ?? null,
        $d['nom'] ?? null,
    ]);

    return implode(' ', $parts);
}

$company = INSEE::findSiren('123456789');
echo getDirectorName($company);
// Output: Jean Dupont or Jean Dupont Marie (if nomUsage is present)
```

### In a Model/DTO

```php
<?php

namespace App\Models;

class CompanyInfo
{
    public function __construct(
        public string $siren,
        public string $denomination,
        public ?string $directorName = null,
        public ?string $directorGender = null,
    ) {}

    public static function fromInseeResponse(array $response): self
    {
        $dirigeant = $response['dirigeant'] ?? null;

        $directorName = null;
        if ($dirigeant) {
            $directorName = sprintf(
                "%s %s",
                $dirigeant['prenom'] ?? '',
                $dirigeant['nom'] ?? ''
            );
        }

        return new self(
            siren: $response['uniteLegale']['siren'],
            denomination: $response['uniteLegale']['denomination'],
            directorName: trim($directorName ?? ''),
            directorGender: $dirigeant['sexe'] ?? null,
        );
    }
}
```

## Gender Handling

The `sexe` field contains gender information:

- `M` = Male (Masculin)
- `F` = Female (Féminin)

Use this for personalization:

```php
$company = INSEE::findSiren('123456789');

if (isset($company['dirigeant'])) {
    $d = $company['dirigeant'];
    $title = ($d['sexe'] === 'M') ? 'M.' : 'Mme';
    
    echo "$title " . $d['prenom'] . " " . $d['nom'];
}
```

## Common Use Cases

### Display Business Owner Information

```php
function displayBusinessOwner(array $inseeResponse): string
{
    if (!isset($inseeResponse['dirigeant'])) {
        return "Business Entity (not a natural person)";
    }

    $d = $inseeResponse['dirigeant'];
    return sprintf(
        "%s %s",
        ucfirst(strtolower($d['prenom'] ?? '')),
        strtoupper($d['nom'] ?? '')
    );
}
```

### Filter Results by Natural Persons

```php
$results = INSEE::searchCompanies(['q' => 'consultant']);

$naturalPersons = array_filter(
    $results['unitesLegales'],
    fn($company) => isset($company['dirigeant'])
);

foreach ($naturalPersons as $company) {
    echo $company['dirigeant']['prenom'] . " - " . 
         $company['denomination'];
}
```

### Contact Information Extraction

```php
function extractContact(array $inseeResponse): array
{
    return [
        'type' => isset($inseeResponse['dirigeant']) ? 'natural_person' : 'legal_entity',
        'name' => $inseeResponse['uniteLegale']['denomination'] ?? 'Unknown',
        'contact_name' => isset($inseeResponse['dirigeant'])
            ? sprintf(
                "%s %s",
                $inseeResponse['dirigeant']['prenom'] ?? '',
                $inseeResponse['dirigeant']['nom'] ?? ''
            )
            : null,
        'siren' => $inseeResponse['uniteLegale']['siren'] ?? null,
    ];
}
```

## Important Notes

1. **Not all natural persons have complete data** - Some fields may be null or missing
2. **Privacy considerations** - Handle personal names with appropriate data protection
3. **Case preservation** - Name data may be in various cases; normalize as needed
4. **Nom vs Surname** - "nomUsage" is the surname/maiden name; "nom" is the legal family name
5. **Multiple dirigeants** - The package injects the primary dirigeant; INSEE may have additional data not exposed

## Troubleshooting

### No Dirigeant Appears

The entity may not be a natural person:

```php
$company = INSEE::findSiren('999999999');

if (!isset($company['dirigeant'])) {
    echo $company['uniteLegale']['categorieJuridiqueUniteLegale'];
    // Check the legal entity type to confirm
}
```

### Incomplete Dirigeant Data

Some fields may be null:

```php
$name = implode(' ', array_filter([
    $company['dirigeant']['prenom'] ?? null,
    $company['dirigeant']['nom'] ?? null,
]));
```

### Encoding Issues

Names may contain accents and special characters:

```php
// Ensure UTF-8 encoding
$name = htmlspecialchars(
    $company['dirigeant']['prenom'] . ' ' . 
    $company['dirigeant']['nom'],
    ENT_QUOTES,
    'UTF-8'
);
```

## Tips

- Always check for dirigeant existence; not all responses will have it
- Use array filtering for robust access to potentially null fields
- Consider caching dirigeant data if you display it frequently
- Normalize names (case, encoding) before displaying or storing
- Respect privacy regulations when storing personal name data
