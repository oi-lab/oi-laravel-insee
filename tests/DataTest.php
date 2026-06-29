<?php

use Illuminate\Support\Facades\Http;
use OiLab\OiLaravelInsee\Client;
use OiLab\OiLaravelInsee\Data\Dirigeant;
use OiLab\OiLaravelInsee\Data\Etablissement;
use OiLab\OiLaravelInsee\Data\PeriodeUniteLegale;
use OiLab\OiLaravelInsee\Data\SirenResponse;
use OiLab\OiLaravelInsee\Data\SirenSearchResponse;
use OiLab\OiLaravelInsee\Data\SiretResponse;
use OiLab\OiLaravelInsee\Data\SiretSearchResponse;
use OiLab\OiLaravelInsee\Data\UniteLegale;

it('returns a typed SiretResponse from siret()', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/12345678901234' => Http::response([
            'header' => ['statut' => 200, 'message' => 'OK'],
            'etablissement' => [
                'siren' => '123456789',
                'nic' => '01234',
                'siret' => '12345678901234',
                'etablissementSiege' => true,
                'adresseEtablissement' => [
                    'numeroVoieEtablissement' => '10',
                    'typeVoieEtablissement' => 'RUE',
                    'libelleVoieEtablissement' => 'DE LA PAIX',
                    'codePostalEtablissement' => '75002',
                    'libelleCommuneEtablissement' => 'PARIS',
                ],
                'uniteLegale' => [
                    'denominationUniteLegale' => 'ACME SAS',
                ],
            ],
        ], 200),
    ]);

    $response = (new Client('test-secret'))->siret('12345678901234');

    expect($response)->toBeInstanceOf(SiretResponse::class)
        ->and($response->header->statut)->toBe(200)
        ->and($response->etablissement)->toBeInstanceOf(Etablissement::class)
        ->and($response->etablissement->siret)->toBe('12345678901234')
        ->and($response->etablissement->etablissementSiege)->toBeTrue()
        ->and($response->etablissement->adresseEtablissement->codePostalEtablissement)->toBe('75002')
        ->and($response->etablissement->uniteLegale)->toBeInstanceOf(UniteLegale::class)
        ->and($response->etablissement->uniteLegale->denominationUniteLegale)->toBe('ACME SAS');
});

it('returns a typed SirenResponse with a Dirigeant for a personne physique', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'nomUniteLegale' => 'DURAND',
                'prenomUsuelUniteLegale' => 'Marie',
                'sexeUniteLegale' => 'F',
            ],
        ], 200),
    ]);

    $response = (new Client('test-secret'))->siren('123456789');

    expect($response)->toBeInstanceOf(SirenResponse::class)
        ->and($response->uniteLegale)->toBeInstanceOf(UniteLegale::class)
        ->and($response->uniteLegale->siren)->toBe('123456789')
        ->and($response->uniteLegale->dirigeant)->toBeInstanceOf(Dirigeant::class)
        ->and($response->uniteLegale->dirigeant->nom)->toBe('DURAND')
        ->and($response->uniteLegale->dirigeant->prenom)->toBe('Marie')
        ->and($response->uniteLegale->dirigeant->sexe)->toBe('F');
});

it('leaves dirigeant null for a personne morale', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'denominationUniteLegale' => 'ACME SAS',
            ],
        ], 200),
    ]);

    $response = (new Client('test-secret'))->siren('123456789');

    expect($response->uniteLegale->dirigeant)->toBeNull()
        ->and($response->uniteLegale->denominationUniteLegale)->toBe('ACME SAS');
});

it('casts periodesUniteLegale into typed entries', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren/123456789' => Http::response([
            'header' => ['statut' => 200],
            'uniteLegale' => [
                'siren' => '123456789',
                'periodesUniteLegale' => [
                    [
                        'dateDebut' => '2020-01-01',
                        'denominationUniteLegale' => 'ACME SAS',
                        'etatAdministratifUniteLegale' => 'A',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = (new Client('test-secret'))->siren('123456789');

    expect($response->uniteLegale->periodesUniteLegale)->toHaveCount(1)
        ->and($response->uniteLegale->periodesUniteLegale[0])
        ->toBeInstanceOf(PeriodeUniteLegale::class)
        ->and($response->uniteLegale->periodesUniteLegale[0]->denominationUniteLegale)->toBe('ACME SAS');
});

it('returns a typed SirenSearchResponse list from companies()', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siren*' => Http::response([
            'header' => ['statut' => 200, 'total' => 2, 'nombre' => 2],
            'unitesLegales' => [
                ['siren' => '111111111', 'nomUniteLegale' => 'DUPONT', 'prenomUsuelUniteLegale' => 'Jean'],
                ['siren' => '222222222', 'denominationUniteLegale' => 'ACME SAS'],
            ],
        ], 200),
    ]);

    $response = (new Client('test-secret'))->companies(['q' => 'denomination:*']);

    expect($response)->toBeInstanceOf(SirenSearchResponse::class)
        ->and($response->header->total)->toBe(2)
        ->and($response->unitesLegales)->toHaveCount(2)
        ->and($response->unitesLegales[0])->toBeInstanceOf(UniteLegale::class)
        ->and($response->unitesLegales[0]->dirigeant->nom)->toBe('DUPONT')
        ->and($response->unitesLegales[1]->dirigeant)->toBeNull();
});

it('returns a typed SiretSearchResponse list from establishments()', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret*' => Http::response([
            'header' => ['statut' => 200, 'total' => 1, 'nombre' => 1],
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

    $response = (new Client('test-secret'))->establishments(['q' => 'nomUniteLegale:BERNARD']);

    expect($response)->toBeInstanceOf(SiretSearchResponse::class)
        ->and($response->etablissements)->toHaveCount(1)
        ->and($response->etablissements[0])->toBeInstanceOf(Etablissement::class)
        ->and($response->etablissements[0]->uniteLegale->dirigeant->nom)->toBe('BERNARD');
});

it('maps an error response onto the header without a payload', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/00000000000000' => Http::response([
            'header' => ['statut' => 404, 'message' => 'Not found'],
        ], 404),
    ]);

    $response = (new Client('test-secret'))->siret('00000000000000');

    expect($response->header->statut)->toBe(404)
        ->and($response->header->message)->toBe('Not found')
        ->and($response->etablissement)->toBeNull();
});
