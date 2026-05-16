---
title: Search Query Syntax
description: INSEE query language reference
order: 1
---

# Search Query Syntax

The OI Laravel INSEE package uses the INSEE query language for searching companies and establishments. This document covers the syntax and common use cases.

## Basic Syntax

### Simple Search

A simple query searches all indexed fields for the given term:

```php
INSEE::searchCompanies(['q' => 'Apple'])
```

### Wildcard Search

Use `*` as a wildcard to match partial terms:

```php
INSEE::searchCompanies(['q' => 'Ap*'])  // Matches: Apple, Applied, etc.
INSEE::searchCompanies(['q' => '*inc*'])  // Matches: Syncing, Inc, etc.
```

### Exact Phrase

Wrap phrases in quotes for exact matching:

```php
INSEE::searchCompanies(['q' => '"Apple France SARL"'])
```

### Field-Specific Search

Search within a specific field using `field:value` syntax:

```php
INSEE::searchCompanies(['q' => 'siren:732829320'])
INSEE::searchCompanies(['q' => 'denomination:Apple'])
INSEE::searchEstablishments(['q' => 'codePostalEtablissement:75008'])
```

## Logical Operators

### AND Operator

Combine multiple conditions with `AND`:

```php
// All results must match BOTH conditions
INSEE::searchCompanies([
    'q' => 'denomination:Apple AND etatAdministratifUniteLegale:A'
])
```

Multiple space-separated terms are treated as AND by default:

```php
// Equivalent to: denomination:Apple AND etatAdministratifUniteLegale:A
INSEE::searchCompanies(['q' => 'denomination:Apple etatAdministratifUniteLegale:A'])
```

### OR Operator

Use `OR` to match either condition:

```php
// Results matching EITHER condition
INSEE::searchCompanies([
    'q' => 'denomination:Apple OR denomination:Microsoft'
])
```

### NOT Operator

Use `NOT` to exclude results:

```php
// Results matching first condition but NOT second
INSEE::searchCompanies([
    'q' => 'denomination:Apple NOT etatAdministratifUniteLegale:F'
])
```

### Grouping with Parentheses

Combine operators with parentheses:

```php
INSEE::searchCompanies([
    'q' => '(denomination:Apple OR denomination:Microsoft) AND etatAdministratifUniteLegale:A'
])
```

## Common Company Fields

Use these fields for searching companies (legal units):

| Field | Description | Example |
|-------|-------------|---------|
| `siren` | 9-digit company identifier | `siren:732829320` |
| `denomination` | Official company name | `denomination:"Apple France"` |
| `categorieJuridiqueUniteLegale` | Legal entity type code | `categorieJuridiqueUniteLegale:5699` |
| `activitePrincipaleUniteLegale` | NAF business activity code | `activitePrincipaleUniteLegale:6311Z` |
| `etatAdministratifUniteLegale` | Status (A=active, F=closed) | `etatAdministratifUniteLegale:A` |
| `trancheEffectifsUniteLegale` | Employee range code | `trancheEffectifsUniteLegale:52` |

## Common Establishment Fields

Use these fields for searching establishments:

| Field | Description | Example |
|-------|-------------|---------|
| `siret` | 14-digit establishment identifier | `siret:73282932000074` |
| `siren` | Parent company 9-digit identifier | `siren:732829320` |
| `denominationUsuelleEtablissement` | Establishment trading name | `denominationUsuelleEtablissement:Apple` |
| `codePostalEtablissement` | Postal code | `codePostalEtablissement:75008` |
| `libelleCommuneEtablissement` | City name | `libelleCommuneEtablissement:PARIS` |
| `etatAdministratifEtablissement` | Status (A=active, F=closed) | `etatAdministratifEtablissement:A` |
| `activitePrincipaleEtablissement` | NAF business activity code | `activitePrincipaleEtablissement:4791B` |

## Complex Query Examples

### Find Active Companies Named "Apple"

```php
INSEE::searchCompanies([
    'q' => 'denomination:Apple AND etatAdministratifUniteLegale:A',
    'nombre' => 20
])
```

### Find Closed Establishments in Paris

```php
INSEE::searchEstablishments([
    'q' => 'libelleCommuneEtablissement:PARIS AND etatAdministratifEtablissement:F',
    'nombre' => 50
])
```

### Find Tech Companies with 100+ Employees

```php
INSEE::searchCompanies([
    'q' => 'activitePrincipaleUniteLegale:(6311Z OR 6201Z OR 6202Z) AND (trancheEffectifsUniteLegale:31 OR trancheEffectifsUniteLegale:32 OR trancheEffectifsUniteLegale:41 OR trancheEffectifsUniteLegale:42)',
    'nombre' => 100
])
```

### Find Establishments in Specific Postcodes

```php
INSEE::searchEstablishments([
    'q' => '(codePostalEtablissement:75008 OR codePostalEtablissement:75009) AND etatAdministratifEtablissement:A',
    'nombre' => 100
])
```

### Find Companies Matching Multiple Names

```php
INSEE::searchCompanies([
    'q' => '(denomination:Apple OR denomination:Google OR denomination:Microsoft) AND etatAdministratifUniteLegale:A',
    'nombre' => 30
])
```

### Find Tech Companies Not Yet Established

```php
INSEE::searchCompanies([
    'q' => 'activitePrincipaleUniteLegale:6311Z NOT etatAdministratifUniteLegale:F',
    'nombre' => 50
])
```

## Special Characters and Escaping

Some characters have special meaning in queries:

| Character | Meaning | Use |
|-----------|---------|-----|
| `:` | Field separator | `field:value` |
| `*` | Wildcard | `Ap*ple` |
| `"` | Phrase delimiter | `"exact phrase"` |
| `(` `)` | Grouping | `(a OR b) AND c` |
| `AND` `OR` `NOT` | Operators | Query logic |

If you need to search for these characters literally, they may need to be escaped. However, it's usually better to use field-specific searches when possible.

## Best Practices

1. **Use field-specific searches** when you know what you're searching for
   ```php
   // Good
   INSEE::searchCompanies(['q' => 'siren:732829320'])
   
   // Less precise
   INSEE::searchCompanies(['q' => '732829320'])
   ```

2. **Use exact phrases** for company names with spaces
   ```php
   // Good
   INSEE::searchCompanies(['q' => 'denomination:"Apple France SARL"'])
   
   // May return partial matches
   INSEE::searchCompanies(['q' => 'Apple France SARL'])
   ```

3. **Limit the status** to active establishments when relevant
   ```php
   INSEE::searchEstablishments([
       'q' => 'libelleCommuneEtablissement:PARIS AND etatAdministratifEtablissement:A'
   ])
   ```

4. **Use AND to narrow results** and improve relevance
   ```php
   // Returns broader results
   INSEE::searchCompanies(['q' => 'technology France'])
   
   // More specific
   INSEE::searchCompanies([
       'q' => 'activitePrincipaleUniteLegale:6311Z AND denomination:*tech*'
   ])
   ```

5. **Combine with pagination** for large result sets
   ```php
   INSEE::searchCompanies([
       'q' => 'denomination:Apple',
       'nombre' => 100,
       'debut' => 0
   ])
   ```

## Legal Entity Type Codes (categorieJuridiqueUniteLegale)

Common French business entity types:

| Code | Type |
|------|------|
| 5699 | Société à responsabilité limitée (SARL) |
| 5710 | Société en nom collectif (SNC) |
| 5800 | Société anonyme (SA) |
| 5520 | Société par actions simplifiée (SAS) |
| 8410 | Entrepreneur individuel |
| 8450 | Micro-entrepreneur (auto-entrepreneur) |

Example:

```php
INSEE::searchCompanies([
    'q' => 'categorieJuridiqueUniteLegale:(5710 OR 5800 OR 5520)',
    'nombre' => 50
])
```

## Business Activity Codes (NAF - activitePrincipaleUniteLegale)

NAF codes identify the main business activity. Examples:

| Code | Activity |
|------|----------|
| 6311Z | Processing of data, hosting and related activities |
| 6201Z | Computer programming activities |
| 6202Z | IT consulting activities |
| 4791B | Distance sales by mail order |
| 4711B | Supermarkets |

Example:

```php
INSEE::searchCompanies([
    'q' => 'activitePrincipaleUniteLegale:(6311Z OR 6201Z OR 6202Z)',
    'nombre' => 100
])
```

## Tips

- The INSEE API is case-insensitive for most searches
- Wildcard searches may return many results; use pagination
- Combine multiple criteria to narrow down results
- Not all fields are indexed; refer to INSEE documentation for the most current list
- Complex queries with many OR conditions can be slow; consider pagination
