<?php

namespace OiLab\OiLaravelInsee;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OiLab\OiLaravelInsee\Data\SirenResponse;
use OiLab\OiLaravelInsee\Data\SirenSearchResponse;
use OiLab\OiLaravelInsee\Data\SiretResponse;
use OiLab\OiLaravelInsee\Data\SiretSearchResponse;

class Client
{
    private ?string $accessToken = null;

    public function __construct(
        public string $clientSecret,
        public ?string $clientId = null,
        public string $baseUrl = 'https://api.insee.fr/api-sirene/3.11',
        public int $cacheDuration = 23
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

    private function makeRequest(string $endpoint, array $params = []): array
    {
        $response = Http::withHeader('X-INSEE-Api-Key-Integration', $this->clientSecret)
            ->get($this->baseUrl.$endpoint, $params);

        if (! $response->successful()) {
            return $response->json();
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
