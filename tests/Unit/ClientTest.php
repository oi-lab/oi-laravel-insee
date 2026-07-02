<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OiLab\OiLaravelInsee\Client;

it('can find a SIRET', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/12345678901234' => Http::response([
            'header' => ['statut' => 200],
            'etablissement' => ['siret' => '12345678901234'],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiret('12345678901234');

    expect($result)
        ->toBeArray()
        ->toHaveKey('etablissement');
});

it('can find a SIREN', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => ['siren' => '123456789'],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiren('123456789');

    expect($result)
        ->toBeArray()
        ->toHaveKey('uniteLegale');
});

it('can search companies', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren*' => Http::response([
            'header' => ['statut' => 200, 'total' => 1],
            'unitesLegales' => [['siren' => '123456789']],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->searchCompanies(['q' => 'denomination:ACME']);

    expect($result)
        ->toBeArray()
        ->toHaveKey('unitesLegales');
});

it('can search establishments', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret*' => Http::response([
            'header' => ['statut' => 200, 'total' => 1],
            'etablissements' => [['siret' => '12345678901234']],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->searchEstablishments(['q' => 'denominationUniteLegale:ACME']);

    expect($result)
        ->toBeArray()
        ->toHaveKey('etablissements');
});

it('can get API status', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/informations' => Http::response([
            'status' => 'OK',
            'version' => '3.11',
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->getApiStatus();

    expect($result)
        ->toBeArray()
        ->toHaveKey('status');
});

it('sends the correct authentication header', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/informations' => Http::response(['status' => 'OK'], 200),
    ]);

    $client = new Client('my-secret-key');
    $client->getApiStatus();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-INSEE-Api-Key-Integration', 'my-secret-key');
    });
});

it('returns error response when API call fails', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/00000000000000' => Http::response([
            'header' => ['statut' => 404, 'message' => 'Not found'],
        ], 404),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiret('00000000000000');

    expect($result)
        ->toBeArray()
        ->toHaveKey('header');
});

it('can get cached access token', function () {
    Cache::shouldReceive('has')
        ->once()
        ->with('insee_access_token')
        ->andReturn(true);

    Cache::shouldReceive('get')
        ->once()
        ->with('insee_access_token')
        ->andReturn('cached-token-123');

    $client = new Client('test-secret', 'test-client-id');
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $token = $method->invoke($client);

    expect($token)->toBe('cached-token-123');
});

it('can obtain and cache new access token', function () {
    Cache::shouldReceive('has')
        ->once()
        ->with('insee_access_token')
        ->andReturn(false);

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(function ($key, $value, $ttl) {
            return $key === 'insee_access_token' && $value === 'new-token-456';
        });

    Http::fake([
        'api.insee.fr/token' => Http::response([
            'access_token' => 'new-token-456',
            'token_type' => 'Bearer',
            'expires_in' => 86400,
        ], 200),
    ]);

    $client = new Client('test-secret', 'test-client-id');
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $token = $method->invoke($client);

    expect($token)->toBe('new-token-456');
});

it('extracts dirigeant from a personne physique on findSiret', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/12345678901234' => Http::response([
            'header' => ['statut' => 200],
            'etablissement' => [
                'siret' => '12345678901234',
                'uniteLegale' => [
                    'denominationUniteLegale' => null,
                    'nomUniteLegale' => 'DUPONT',
                    'nomUsageUniteLegale' => 'MARTIN',
                    'prenomUsuelUniteLegale' => 'Jean',
                    'prenom1UniteLegale' => 'Jean',
                    'sexeUniteLegale' => 'M',
                ],
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiret('12345678901234');

    expect($result['etablissement']['uniteLegale']['dirigeant'])
        ->toBe([
            'nom' => 'DUPONT',
            'nomUsage' => 'MARTIN',
            'prenom' => 'Jean',
            'sexe' => 'M',
        ]);
});

it('extracts dirigeant from a personne physique on findSiren', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'denominationUniteLegale' => null,
                'nomUniteLegale' => 'DURAND',
                'nomUsageUniteLegale' => null,
                'prenomUsuelUniteLegale' => 'Marie',
                'sexeUniteLegale' => 'F',
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiren('123456789');

    expect($result['uniteLegale']['dirigeant'])
        ->toBe([
            'nom' => 'DURAND',
            'nomUsage' => null,
            'prenom' => 'Marie',
            'sexe' => 'F',
        ]);
});

it('falls back to prenom1UniteLegale when prenomUsuelUniteLegale is missing', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'nomUniteLegale' => 'PETIT',
                'prenom1UniteLegale' => 'Paul',
                'sexeUniteLegale' => 'M',
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiren('123456789');

    expect($result['uniteLegale']['dirigeant']['prenom'])->toBe('Paul');
});

it('does not inject dirigeant for a personne morale', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'denominationUniteLegale' => 'ACME SAS',
                'nomUniteLegale' => null,
                'prenomUsuelUniteLegale' => null,
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiren('123456789');

    expect($result['uniteLegale'])->not->toHaveKey('dirigeant');
});

it('injects dirigeant for each item in searchCompanies results', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren*' => Http::response([
            'header' => ['statut' => 200, 'total' => 2],
            'unitesLegales' => [
                [
                    'siren' => '111111111',
                    'nomUniteLegale' => 'DUPONT',
                    'prenomUsuelUniteLegale' => 'Jean',
                ],
                [
                    'siren' => '222222222',
                    'denominationUniteLegale' => 'ACME SAS',
                    'nomUniteLegale' => null,
                ],
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->searchCompanies(['q' => 'denomination:*']);

    expect($result['unitesLegales'][0]['dirigeant']['nom'])->toBe('DUPONT')
        ->and($result['unitesLegales'][0]['dirigeant']['prenom'])->toBe('Jean')
        ->and($result['unitesLegales'][1])->not->toHaveKey('dirigeant');
});

it('injects dirigeant for each item in searchEstablishments results', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret*' => Http::response([
            'header' => ['statut' => 200, 'total' => 1],
            'etablissements' => [
                [
                    'siret' => '12345678901234',
                    'uniteLegale' => [
                        'nomUniteLegale' => 'BERNARD',
                        'prenomUsuelUniteLegale' => 'Sophie',
                        'sexeUniteLegale' => 'F',
                    ],
                ],
            ],
        ], 200),
    ]);

    $client = new Client('test-secret');
    $result = $client->searchEstablishments(['q' => 'nomUniteLegale:BERNARD']);

    expect($result['etablissements'][0]['uniteLegale']['dirigeant'])
        ->toBe([
            'nom' => 'BERNARD',
            'nomUsage' => null,
            'prenom' => 'Sophie',
            'sexe' => 'F',
        ]);
});

it('does not enrich error responses', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/00000000000000' => Http::response([
            'header' => ['statut' => 404, 'message' => 'Not found'],
        ], 404),
    ]);

    $client = new Client('test-secret');
    $result = $client->findSiret('00000000000000');

    expect($result)->not->toHaveKey('etablissement');
});

it('throws exception when token request fails', function () {
    Cache::shouldReceive('has')
        ->once()
        ->with('insee_access_token')
        ->andReturn(false);

    Http::fake([
        'api.insee.fr/token' => Http::response([], 401),
    ]);

    $client = new Client('test-secret', 'test-client-id');
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);

    $method->invoke($client);
})->throws(Exception::class, 'Failed to obtain INSEE API access token');
