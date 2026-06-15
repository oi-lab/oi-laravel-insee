# Laravel INSEE

Use the `oi-laravel-insee` package to query the French INSEE SIRENE API for companies (SIREN) and
establishments (SIRET). Resolve the client through the `Insee` facade, dependency injection on
`OiLab\OiLaravelInsee\Client`, or `app('insee')` — never instantiate the client manually. Methods
(`findSiret`, `findSiren`, `searchCompanies`, `searchEstablishments`, `getApiStatus`) return arrays;
the `q` search parameter uses INSEE's `field:value` syntax with `AND`/`OR`/`*`.

- IMPORTANT: Activate `oilab-laravel-insee` when working with SIREN/SIRET lookups, French company or
  establishment data, or the INSEE SIRENE API in this Laravel application.
