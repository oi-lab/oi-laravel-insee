# OI Laravel INSEE — AI Context

This package integrates the French **INSEE SIRENE API** to look up companies (SIREN) and establishments
(SIRET). It exposes a `Client`, an `Insee` facade, and an `insee` container binding.

## Core Concepts

- **SIREN** — 9-digit identifier of a legal unit (company).
- **SIRET** — 14-digit identifier of an establishment (a SIREN + a 5-digit NIC).
- **Client** — `OiLab\OiLaravelInsee\Client`, resolved from the container (singleton). Handles OAuth/token
  auth, caching, and the SIRENE HTTP calls.
- **Insee facade** — `OiLab\OiLaravelInsee\Facades\Insee`, a thin static entry point to the `Client`.

## Resolving the Client

Prefer dependency injection or the facade over `new Client(...)`:

```php
use OiLab\OiLaravelInsee\Facades\Insee;     // facade
use OiLab\OiLaravelInsee\Client;            // type-hint for DI

// or the container binding
$client = app('insee');
```

## Available Methods

```php
Insee::findSiret('12345678901234');   // establishment by SIRET (14 digits)
Insee::findSiren('123456789');        // company by SIREN (9 digits)
Insee::searchCompanies(['q' => 'denomination:ACME', 'nombre' => 20, 'debut' => 0]);
Insee::searchEstablishments(['q' => 'denominationUniteLegale:ACME AND codePostalEtablissement:75001']);
Insee::getApiStatus();                // current API status
```

All methods return the decoded API response as an `array`.

## Typed Methods (spatie/laravel-data DTOs)

Each lookup has a typed counterpart returning a `spatie/laravel-data` DTO from the
`OiLab\OiLaravelInsee\Data` namespace. Prefer these when you want typed access /
IDE autocompletion; use the array methods when you need the raw payload.

```php
Insee::siret('12345678901234');                 // SiretResponse  (≈ findSiret)
Insee::siren('123456789');                      // SirenResponse  (≈ findSiren)
Insee::companies(['q' => 'denomination:ACME']); // SirenSearchResponse (≈ searchCompanies)
Insee::establishments(['q' => '...']);          // SiretSearchResponse (≈ searchEstablishments)
```

Shape: `SiretResponse{ header: ?ResponseHeader, etablissement: ?Etablissement }`,
`SirenResponse{ header, uniteLegale: ?UniteLegale }`, and the two search responses
wrap `etablissements: Etablissement[]` / `unitesLegales: UniteLegale[]`. An
`Etablissement` carries `uniteLegale`, `adresseEtablissement`/`adresse2Etablissement`
(`AdresseEtablissement`) and `periodesEtablissement` (`PeriodeEtablissement[]`); a
`UniteLegale` carries `periodesUniteLegale` (`PeriodeUniteLegale[]`) and a nullable
`dirigeant` (`Dirigeant`). All fields are nullable, so partial responses never throw.
Call `->toArray()` / `->toJson()` to serialize back.

## Search Query Syntax

The `q` parameter uses INSEE's field:value syntax:

```php
'q' => 'denomination:ACME'                                            // single field
'q' => 'denomination:ACME AND codePostalEtablissement:75001'          // AND
'q' => 'codePostalEtablissement:75001 OR codePostalEtablissement:75002' // OR
'q' => 'denomination:ACM*'                                            // wildcard
```

Common fields — companies: `siren`, `denomination`, `categorieJuridiqueUniteLegale`,
`activitePrincipaleUniteLegale`. Establishments: `siret`, `denominationUniteLegale`,
`codePostalEtablissement`, `activitePrincipaleEtablissement`, `etatAdministratifEtablissement`
(`A`=active, `F`=closed).

## Dirigeant Injection (Natural Persons Only)

For natural persons (entrepreneur individuel, micro-entrepreneur, EIRL), the package injects a `dirigeant`
key into every `uniteLegale` node:

```php
$result['uniteLegale']['dirigeant'];
// ['nom' => 'DUPONT', 'nomUsage' => 'MARTIN'|null, 'prenom' => 'Jean', 'sexe' => 'M'|'F']
```

Injection points: `findSiret` → `etablissement.uniteLegale.dirigeant`; `findSiren` → `uniteLegale.dirigeant`;
`searchCompanies` → `unitesLegales[].dirigeant`; `searchEstablishments` → `etablissements[].uniteLegale.dirigeant`.

**Limitation:** the SIRENE API does not expose directors for legal entities (SAS, SARL, SCI, associations…).
For those, no `dirigeant` key is injected — use a complementary source such as the Recherche d'entreprises API.

## Configuration

Publish the config and set credentials in `.env`:

```bash
php artisan vendor:publish --tag=oi-laravel-insee-config
```

```env
INSEE_CLIENT_SECRET=your-insee-api-key
INSEE_CLIENT_ID=your-client-id        # optional, for OAuth
INSEE_BASE_URL=https://api.insee.fr/api-sirene/3.11   # optional
INSEE_CACHE_DURATION=23               # optional, hours
```

Credentials come from the [INSEE API Portal](https://portail-api.insee.fr/). Read config via
`config('oi-laravel-insee.*')` — never call `env()` outside config files.

## Conventions

- Resolve the client through the `Insee` facade, DI (`Client`), or `app('insee')` — do not instantiate
  `Client` manually.
- Responses are arrays; check `header.statut` (200 = OK) before reading payload keys.
- Responses are cached for `cache_duration` hours; expect cached results on repeated lookups.

## Updating the AI Skill

After updating this package, re-install the skill files:

```bash
php artisan oi:skills
```
