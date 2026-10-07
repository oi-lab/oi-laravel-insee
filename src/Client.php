<?php

namespace OiLab\OiLaravelInsee;

use Exception;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use OiLab\OiLaravelInsee\Data\Etablissement;
use OiLab\OiLaravelInsee\Data\SirenResponse;
use OiLab\OiLaravelInsee\Data\SirenSearchResponse;
use OiLab\OiLaravelInsee\Data\SiretResponse;
use OiLab\OiLaravelInsee\Data\SiretSearchPage;
use OiLab\OiLaravelInsee\Data\SiretSearchResponse;
use OiLab\OiLaravelInsee\Exceptions\InseeQuotaExceededException;
use OiLab\OiLaravelInsee\Exceptions\InseeRequestException;
use OiLab\OiLaravelInsee\Exceptions\InseeUnavailableException;
use OiLab\OiLaravelInsee\Search\SiretSearchCriteria;
use OiLab\OiLaravelInsee\Support\CallPriority;
use OiLab\OiLaravelInsee\Support\InseeRateLimiter;

class Client
{
    private ?string $accessToken = null;

    public function __construct(
        public string $clientSecret,
        public ?string $clientId = null,
        public string $baseUrl = 'https://api.insee.fr/api-sirene/3.11',
        public int $cacheDuration = 23,
        private ?InseeRateLimiter $rateLimiter = null,
    ) {}

    public function findSiret(string $siret): array
    {
        return $this->makeRequest("/siret/{$siret}");
    }

    public function findSiren(string $siren): array
    {
        return $this->makeRequest("/siren/{$siren}");
    }

    public function searchCompanies(array $params): array
    {
        return $this->makeRequest('/siren', $params);
    }

    public function searchEstablishments(array $params): array
    {
        return $this->makeRequest('/siret', $params);
    }

    public function getApiStatus(): array
    {
        return $this->makeRequest('/informations');
    }

    /**
     * Typed counterpart of findSiret().
     */
    public function siret(string $siret): SiretResponse
    {
        return SiretResponse::from($this->findSiret($siret));
    }

    /**
     * Typed counterpart of findSiren().
     */
    public function siren(string $siren): SirenResponse
    {
        return SirenResponse::from($this->findSiren($siren));
    }

    /**
     * Typed counterpart of searchCompanies().
     *
     * @param  array<string, mixed>  $params
     */
    public function companies(array $params): SirenSearchResponse
    {
        return SirenSearchResponse::from($this->searchCompanies($params));
    }

    /**
     * Typed counterpart of searchEstablishments().
     *
     * @param  array<string, mixed>  $params
     */
    public function establishments(array $params): SiretSearchResponse
    {
        return SiretSearchResponse::from($this->searchEstablishments($params));
    }

    /**
     * Walk a multicriteria search on `/siret` page by page, following the
     * cursor (`curseur=*`, then `curseurSuivant`) until the API says there is no
     * more. Each page exposes the cursor to persist to resume the search later;
     * pass it back as `$cursor`.
     *
     * A background search: it stops with an InseeQuotaExceededException at
     * `rate_limits.background_ceiling`, keeping a reserve for unit calls. No
     * result is an empty page, not an exception.
     *
     * @return Generator<int, SiretSearchPage>
     *
     * @throws InseeQuotaExceededException
     * @throws InseeUnavailableException
     * @throws InseeRequestException
     */
    public function searchEstablishmentsLazily(SiretSearchCriteria $criteria, ?string $cursor = null, int $pageSize = 1000): Generator
    {
        $pageSize = max(1, min(1000, $pageSize));
        $current = ($cursor === null || $cursor === '') ? '*' : $cursor;

        do {
            $page = $this->fetchEstablishmentPage($criteria, $current, $pageSize);

            yield $page;

            $current = $page->nextCursor;
        } while (! $page->isLast());
    }

    /**
     * Number of establishments matching the criteria (`nombre=0`, total of the
     * header). A background call, see searchEstablishmentsLazily().
     *
     * @throws InseeQuotaExceededException
     * @throws InseeUnavailableException
     * @throws InseeRequestException
     */
    public function countEstablishments(SiretSearchCriteria $criteria): int
    {
        $response = $this->sendTyped('/siret', $this->searchParameters($criteria, nombre: 0), CallPriority::Background);

        return $response->status() === 404 ? 0 : (int) $response->json('header.total', 0);
    }

    /**
     * Breakdown of the matching establishments by the values of one variable
     * (`facette.champ`), e.g. `trancheEffectifsUniteLegale`.
     *
     * @return array<string, int> value => count
     *
     * @throws InseeQuotaExceededException
     * @throws InseeUnavailableException
     * @throws InseeRequestException
     */
    public function countEstablishmentsBy(SiretSearchCriteria $criteria, string $field): array
    {
        $response = $this->sendTyped('/siret', [...$this->searchParameters($criteria, nombre: 0), 'facette.champ' => $field], CallPriority::Background);

        if ($response->status() === 404) {
            return [];
        }

        $counts = [];

        foreach ($response->json('facettes.0.comptages', []) as $count) {
            $counts[(string) $count['valeur']] = (int) $count['nombre'];
        }

        return $counts;
    }

    /**
     * One establishment by SIRET, or null when the INSEE does not know it. Unlike
     * findSiret() it throws typed exceptions, and it may use the whole hourly
     * quota (it is not stopped by the background ceiling).
     *
     * @throws InseeQuotaExceededException
     * @throws InseeUnavailableException
     * @throws InseeRequestException
     */
    public function establishmentOrFail(string $siret): ?Etablissement
    {
        $response = $this->sendTyped("/siret/{$siret}", [], CallPriority::Unit);

        if ($response->status() === 404) {
            return null;
        }

        $enriched = $this->enrichWithDirigeant($response->json());

        return Etablissement::from($enriched['etablissement'] ?? []);
    }

    private function fetchEstablishmentPage(SiretSearchCriteria $criteria, string $cursor, int $pageSize): SiretSearchPage
    {
        $response = $this->sendTyped('/siret', [...$this->searchParameters($criteria, nombre: $pageSize), 'curseur' => $cursor], CallPriority::Background);

        if ($response->status() === 404) {
            return new SiretSearchPage([], $cursor, $cursor, 0);
        }

        $body = $this->enrichWithDirigeant($response->json());
        $header = $body['header'] ?? [];

        return new SiretSearchPage(
            etablissements: array_map(fn (array $row): Etablissement => Etablissement::from($row), $body['etablissements'] ?? []),
            cursor: (string) ($header['curseur'] ?? $cursor),
            nextCursor: (string) ($header['curseurSuivant'] ?? $cursor),
            total: (int) ($header['total'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function searchParameters(SiretSearchCriteria $criteria, int $nombre): array
    {
        $query = $criteria->toQuery();

        if ($query === '') {
            throw new InvalidArgumentException('A search needs at least one criterion: refusing to query the whole Sirene register.');
        }

        $parameters = ['q' => $query, 'nombre' => $nombre];

        if ($criteria->selectedFields() !== []) {
            $parameters['champs'] = implode(',', $criteria->selectedFields());
        }

        if ($this->setting('mask_null_values', true)) {
            $parameters['masquerValeursNulles'] = 'true';
        }

        return $parameters;
    }

    /**
     * Call the API for the typed methods: limited, retried with a growing delay
     * on 5xx and network errors (never on 4xx), and failing with typed
     * exceptions. A 404 is returned: it means "no result" for a search.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws InseeQuotaExceededException
     * @throws InseeUnavailableException
     * @throws InseeRequestException
     */
    private function sendTyped(string $endpoint, array $parameters, CallPriority $priority): Response
    {
        $delays = array_values((array) $this->setting('retry.delays', [1, 3, 9]));
        $attempt = 0;

        while (true) {
            $this->limiter()->acquire($priority);

            try {
                $response = $this->dispatch($endpoint, $parameters);
            } catch (ConnectionException $e) {
                if (isset($delays[$attempt])) {
                    Sleep::for($delays[$attempt++])->seconds();

                    continue;
                }

                throw new InseeUnavailableException("L'API Sirene est injoignable : {$e->getMessage()}", null, $e);
            }

            $this->limiter()->recordResponse($response);
            $status = $response->status();

            if ($response->successful() || $status === 404) {
                return $response;
            }

            if ($status === 429) {
                throw new InseeQuotaExceededException($this->errorMessage($response), $this->limiter()->markRejected($response));
            }

            if ($status >= 500) {
                if (isset($delays[$attempt])) {
                    Sleep::for($delays[$attempt++])->seconds();

                    continue;
                }

                throw new InseeUnavailableException($this->errorMessage($response), $status);
            }

            throw new InseeRequestException($this->errorMessage($response), $status);
        }
    }

    /**
     * GET, or POST (form-urlencoded) once the URL of a GET would exceed the
     * length URLs are safely accepted at.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function dispatch(string $endpoint, array $parameters): Response
    {
        $request = Http::withHeaders([
            'X-INSEE-Api-Key-Integration' => $this->clientSecret,
            'Accept-Encoding' => 'gzip',
        ])->acceptJson();

        $url = $this->baseUrl.$endpoint;
        $threshold = (int) $this->setting('post_threshold', 2000);

        if ($parameters !== [] && strlen($url.'?'.http_build_query($parameters)) > $threshold) {
            return $request->asForm()->post($url, $parameters);
        }

        return $request->get($url, $parameters);
    }

    private function errorMessage(Response $response): string
    {
        $body = $response->json();

        $message = is_array($body)
            ? ($body['header']['message'] ?? $body['fault']['faultstring'] ?? $body['message'] ?? null)
            : null;

        return (string) ($message ?: "L'API Sirene a répondu {$response->status()}.");
    }

    private function limiter(): InseeRateLimiter
    {
        return $this->rateLimiter ??= InseeRateLimiter::fromConfig();
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return config("oi-laravel-insee.{$key}", $default);
    }

    /**
     * Historical single call, kept as it always was: no exception and no retry,
     * the error body is returned as an array. It goes through the limiter, but
     * never waits more than a few seconds nor throws: it runs inside web
     * requests, where a long sleep would block a worker. An exhausted hourly
     * quota comes back as an INSEE-shaped 429 array.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        try {
            $this->limiter()->acquire(CallPriority::Legacy);
        } catch (InseeQuotaExceededException $e) {
            return ['header' => ['statut' => 429, 'message' => $e->getMessage()]];
        }

        $response = Http::withHeader('X-INSEE-Api-Key-Integration', $this->clientSecret)
            ->get($this->baseUrl.$endpoint, $params);

        $this->limiter()->recordResponse($response);

        if (! $response->successful()) {
            return $response->json() ?? ['header' => ['statut' => $response->status(), 'message' => "L'API Sirene a répondu {$response->status()}."]];
        }

        return $this->enrichWithDirigeant($response->json());
    }

    /**
     * Inject a `dirigeant` key into every `uniteLegale` node of the response
     * when the unit is a natural person (entrepreneur individuel, micro-entrepreneur, EIRL).
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function enrichWithDirigeant(array $response): array
    {
        if (isset($response['uniteLegale']) && is_array($response['uniteLegale'])) {
            $response['uniteLegale'] = $this->injectDirigeant($response['uniteLegale']);
        }

        if (isset($response['etablissement']['uniteLegale']) && is_array($response['etablissement']['uniteLegale'])) {
            $response['etablissement']['uniteLegale'] = $this->injectDirigeant($response['etablissement']['uniteLegale']);
        }

        if (isset($response['unitesLegales']) && is_array($response['unitesLegales'])) {
            foreach ($response['unitesLegales'] as $i => $unite) {
                if (is_array($unite)) {
                    $response['unitesLegales'][$i] = $this->injectDirigeant($unite);
                }
            }
        }

        if (isset($response['etablissements']) && is_array($response['etablissements'])) {
            foreach ($response['etablissements'] as $i => $etab) {
                if (is_array($etab) && isset($etab['uniteLegale']) && is_array($etab['uniteLegale'])) {
                    $response['etablissements'][$i]['uniteLegale'] = $this->injectDirigeant($etab['uniteLegale']);
                }
            }
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $uniteLegale
     * @return array<string, mixed>
     */
    private function injectDirigeant(array $uniteLegale): array
    {
        $dirigeant = $this->extractDirigeant($uniteLegale);

        if ($dirigeant !== null) {
            $uniteLegale['dirigeant'] = $dirigeant;
        }

        return $uniteLegale;
    }

    /**
     * @param  array<string, mixed>  $uniteLegale
     * @return array{nom: string, nomUsage: ?string, prenom: ?string, sexe: ?string}|null
     */
    private function extractDirigeant(array $uniteLegale): ?array
    {
        $nom = $uniteLegale['nomUniteLegale'] ?? null;

        if (! is_string($nom) || $nom === '') {
            return null;
        }

        $prenom = $uniteLegale['prenomUsuelUniteLegale']
            ?? $uniteLegale['prenom1UniteLegale']
            ?? null;

        return [
            'nom' => $nom,
            'nomUsage' => $uniteLegale['nomUsageUniteLegale'] ?? null,
            'prenom' => is_string($prenom) ? $prenom : null,
            'sexe' => $uniteLegale['sexeUniteLegale'] ?? null,
        ];
    }

    private function getAccessToken(): string
    {
        if (Cache::has('insee_access_token')) {
            return Cache::get('insee_access_token');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://api.insee.fr/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new Exception('Failed to obtain INSEE API access token');
        }

        $token = $response->json('access_token');
        Cache::put('insee_access_token', $token, now()->addHours($this->cacheDuration));

        return $token;
    }
}
