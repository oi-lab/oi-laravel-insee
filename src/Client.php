<?php

namespace OiLab\OiLaravelInsee;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

    private function makeRequest(string $endpoint, array $params = []): array
    {
        $response = Http::withHeader('X-INSEE-Api-Key-Integration', $this->clientSecret)
            ->get($this->baseUrl.$endpoint, $params);

        if (! $response->successful()) {
            return $response->json();
        }

        return $response->json();
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
