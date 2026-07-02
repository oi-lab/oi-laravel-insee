# Changelog

All notable changes to `oi-lab/oi-laravel-insee` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
