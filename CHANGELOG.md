# Changelog

All notable changes to `oi-lab/oi-laravel-insee` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-07

### Added

- `SiretSearchCriteria`: typed criteria (`headquartersOnly()`, `activeOnly()`, `publicDiffusionOnly()`, `workforceRanges()`, `communeCodes()`, `postalCodes()`, `departmentCodes()`, `nafCodes()`, `fields()`) building the `q` parameter, with codes validated and more than 1 000 alternatives grouped in parenthesised chunks.
- `Insee::searchEstablishmentsLazily()`: cursor-paginated search yielding `SiretSearchPage` (`etablissements`, `cursor`, `nextCursor`, `total`), resumable from a saved cursor.
- `Insee::countEstablishments()` and `Insee::countEstablishmentsBy()` (`nombre=0`, `facette.champ`).
- `Insee::establishmentOrFail()`: one establishment by SIRET, `null` when unknown, typed exceptions otherwise.
- `WorkforceRange` enum of the INSEE workforce codes, with `min()`, `max()`, `label()` and `within()`.
- Two-window rate limiter (30 per minute, 2 000 per hour) shared by every call and every process through the cache store, kept in line with the `x-quota-*` and `x-rate-limit-*` headers. Configurable background ceiling (`rate_limits.background_ceiling`) that keeps a reserve for unit calls.
- `InseeException`, `InseeQuotaExceededException` (with `retryAt`), `InseeUnavailableException` and `InseeRequestException` for the typed methods.
- Retries with growing delays (1 s, 3 s, 9 s) on 5xx and network errors, never on 4xx.
- Automatic `POST` (form-urlencoded) for searches whose URL would exceed `post_threshold`; `champs`, `masquerValeursNulles=true` and gzip on searches.
- `rate_limits`, `retry`, `post_threshold` and `mask_null_values` configuration keys (defaults apply when an already published config lacks them).
- Documentation of the behaviour of the real API (quota headers, `periode(...)`, departments wildcard, authentication).

### Changed

- The historical methods (`findSiret`, `findSiren`, `searchCompanies`, `searchEstablishments`, `getApiStatus` and their typed counterparts) behave as before, with two additions: they go through the shared limiter, never throwing nor waiting more than `rate_limits.legacy_max_wait_seconds` (3 s) when the minute window is full, and an exhausted hourly quota comes back as an INSEE-shaped `header.statut = 429` array. A non-JSON error body now yields an error array instead of a `TypeError`.

## [1.0.7] - 2026-07-02

### Added

- CI workflow (`tests.yml`) covering PHP 8.2–8.4 × Laravel 11–13.
- `phpunit.xml` with strict flags and split `Unit`/`Feature` testsuites.
- `pint.json` (Laravel preset) and `test`/`lint` composer scripts.
- This changelog.

### Changed

- Moved `ClientTest` and `DataTest` into `tests/Unit/`.
- Homogenized `composer.json` to the OI Lab standard (author email, dependency pins, `minimum-stability`).

## [1.0.0] - 2026-04-16

### Added

- `Client` wrapping the French INSEE SIRENE API with `Insee` facade and `insee` container binding.
- Company lookup by SIREN (`findSiren`/`siren`) and establishment lookup by SIRET (`findSiret`/`siret`).
- Full-text search over companies (`searchCompanies`/`companies`) and establishments (`searchEstablishments`/`establishments`).
- API status endpoint (`getApiStatus`).
- Typed responses via `spatie/laravel-data` DTOs (`SirenResponse`, `SiretResponse`, `SirenSearchResponse`, `SiretSearchResponse`, `UniteLegale`, `Etablissement`, `Dirigeant`, and related objects).
- Automatic `dirigeant` extraction for natural persons (entrepreneur individuel, micro-entrepreneur, EIRL).
- Access-token caching for OAuth-based authentication.
- `oi-insee:install-ai-skill` command and bundled AI-assistant skill.
- Support for PHP 8.2–8.4 and Laravel 11, 12, and 13.
- Test suite of 28 Pest tests.
