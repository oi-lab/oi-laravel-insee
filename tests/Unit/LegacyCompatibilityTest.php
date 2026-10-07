<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use OiLab\OiLaravelInsee\Client;

it('legacy findSiret still returns the error array without throwing', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 404, 'message' => 'Aucun élément trouvé pour siret=00000000000000']], 404)]);

    $result = (new Client('test-secret'))->findSiret('00000000000000');

    expect($result)->toBe(['header' => ['statut' => 404, 'message' => 'Aucun élément trouvé pour siret=00000000000000']]);
});

it('legacy calls do not retry and hand back the error body of a 5xx', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['fault' => ['faultstring' => 'Service Unavailable']], 503)]);

    expect((new Client('test-secret'))->findSiren('123456789'))->toBe(['fault' => ['faultstring' => 'Service Unavailable']]);
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('legacy calls return an error array when the body is not JSON', function () {
    Http::fake(['api.insee.fr/*' => Http::response('<html>Bad gateway</html>', 502)]);

    expect((new Client('test-secret'))->findSiret('12345678901234')['header']['statut'])->toBe(502);
});

it('legacy calls never throw nor wait long when the hourly window is exhausted', function () {
    config()->set('oi-laravel-insee.rate_limits.per_hour', 2);
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200], 'etablissement' => ['siret' => '12345678901234']])]);
    $client = new Client('test-secret');

    $client->findSiret('12345678901234');
    $client->findSiret('12345678901234');
    $result = $client->findSiret('12345678901234');

    expect($result['header']['statut'])->toBe(429)
        ->and($result['header']['message'])->toContain('quota')
        ->and($result)->not->toHaveKey('etablissement');
    Http::assertSentCount(2);
    Sleep::assertNeverSlept();
});

it('legacy calls hand back the 429 array of the exhausted quota headers', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200]], 200, ['x-quota-remaining' => '0', 'x-quota-reset' => (string) (now()->addMinutes(30)->getTimestamp() * 1000)])]);
    $client = new Client('test-secret');
    $client->findSiret('12345678901234');

    expect($client->findSiren('123456789')['header']['statut'])->toBe(429);
    Http::assertSentCount(1);
});

it('legacy calls wait a few seconds at most when the minute window is full, then call anyway', function () {
    config()->set('oi-laravel-insee.rate_limits.per_minute', 1);
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200], 'etablissement' => ['siret' => '12345678901234']])]);
    $client = new Client('test-secret');

    $client->findSiret('12345678901234');
    $result = $client->findSiret('12345678901234');

    expect($result['header']['statut'])->toBe(200);
    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::for(3)->seconds()]);
});

it('legacy calls still enrich a natural person with a dirigeant', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200], 'etablissement' => [
        'siret' => '12345678901234',
        'uniteLegale' => ['nomUniteLegale' => 'DUPONT', 'prenomUsuelUniteLegale' => 'MARIE', 'sexeUniteLegale' => 'F'],
    ]])]);

    $result = (new Client('test-secret'))->findSiret('12345678901234');

    expect($result['etablissement']['uniteLegale']['dirigeant'])->toBe(['nom' => 'DUPONT', 'nomUsage' => null, 'prenom' => 'MARIE', 'sexe' => 'F']);
});

it('legacy search methods keep sending a plain GET', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200], 'etablissements' => []])]);
    $communes = implode(' OR ', array_map(fn (int $i) => "codeCommuneEtablissement:{$i}", range(10001, 10400)));

    (new Client('test-secret'))->searchEstablishments(['q' => $communes]);

    Http::assertSent(fn ($request) => $request->method() === 'GET');
});
