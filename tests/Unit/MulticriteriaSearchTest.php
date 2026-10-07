<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use OiLab\OiLaravelInsee\Client;
use OiLab\OiLaravelInsee\Data\Etablissement;
use OiLab\OiLaravelInsee\Exceptions\InseeException;
use OiLab\OiLaravelInsee\Exceptions\InseeQuotaExceededException;
use OiLab\OiLaravelInsee\Exceptions\InseeRequestException;
use OiLab\OiLaravelInsee\Exceptions\InseeUnavailableException;
use OiLab\OiLaravelInsee\Search\SiretSearchCriteria;

function criteria(): SiretSearchCriteria
{
    return SiretSearchCriteria::make()->headquartersOnly()->communeCodes(['34172']);
}

function pageBody(string $cursor, string $next, array $sirets, int $total = 5): array
{
    return [
        'header' => ['statut' => 200, 'message' => 'OK', 'total' => $total, 'debut' => 0, 'nombre' => count($sirets), 'curseur' => $cursor, 'curseurSuivant' => $next],
        'etablissements' => array_map(fn (string $siret) => ['siret' => $siret, 'siren' => substr($siret, 0, 9)], $sirets),
    ];
}

it('iterates pages by cursor until curseurSuivant equals curseur', function () {
    Http::fake(['api.insee.fr/*' => Http::sequence()
        ->push(pageBody('*', 'AAA', ['11111111100011', '22222222200022']))
        ->push(pageBody('AAA', 'BBB', ['33333333300033', '44444444400044']))
        ->push(pageBody('BBB', 'BBB', ['55555555500055'])),
    ]);

    $pages = iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(criteria(), pageSize: 2), preserve_keys: false);

    expect($pages)->toHaveCount(3)
        ->and($pages[0]->cursor)->toBe('*')
        ->and($pages[0]->nextCursor)->toBe('AAA')
        ->and($pages[0]->total)->toBe(5)
        ->and($pages[0]->etablissements[0])->toBeInstanceOf(Etablissement::class)
        ->and($pages[0]->etablissements[0]->siret)->toBe('11111111100011')
        ->and($pages[2]->isLast())->toBeTrue()
        ->and(collect($pages)->sum(fn ($page) => count($page->etablissements)))->toBe(5);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => $request['curseur'] === '*' && (int) $request['nombre'] === 2);
    Http::assertSent(fn (Request $request) => $request['curseur'] === 'BBB');
});

it('resumes from a given cursor', function () {
    Http::fake(['api.insee.fr/*' => Http::response(pageBody('AAA', 'AAA', ['55555555500055']))]);

    $pages = iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(criteria(), 'AAA'), preserve_keys: false);

    expect($pages)->toHaveCount(1)->and($pages[0]->cursor)->toBe('AAA');
    Http::assertSent(fn (Request $request) => $request['curseur'] === 'AAA');
});

it('sends the query, the fields, the null mask and gzip', function () {
    Http::fake(['api.insee.fr/*' => Http::response(pageBody('*', '*', []))]);

    iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(criteria()->fields(['siret', 'siren'])));

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.insee.fr/api-sirene/3.11/siret?')
        && $request['q'] === 'etablissementSiege:true AND codeCommuneEtablissement:34172'
        && $request['champs'] === 'siret,siren'
        && $request['masquerValeursNulles'] === 'true'
        && (int) $request['nombre'] === 1000
        && $request->hasHeader('X-INSEE-Api-Key-Integration', 'test-secret')
        && $request->hasHeader('Accept-Encoding', 'gzip'));
});

it('returns an empty page, not an exception, when nothing matches', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 404, 'message' => 'Aucun élément trouvé pour q=...']], 404)]);

    $pages = iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(criteria()), preserve_keys: false);

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->etablissements)->toBe([])
        ->and($pages[0]->total)->toBe(0)
        ->and($pages[0]->isLast())->toBeTrue();
});

it('refuses to search the whole register', function () {
    iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(SiretSearchCriteria::make()));
})->throws(InvalidArgumentException::class);

it('switches to POST when the query is too long', function () {
    Http::fake(['api.insee.fr/*' => Http::response(pageBody('*', '*', []))]);
    $communes = array_map(fn (int $i) => sprintf('%05d', 10000 + $i), range(1, 400));

    iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(SiretSearchCriteria::make()->communeCodes($communes)));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.insee.fr/api-sirene/3.11/siret'
        && $request->isForm()
        && str_contains($request['q'], 'codeCommuneEtablissement:10400')
        && $request['curseur'] === '*');
});

it('counts establishments with nombre=0', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200, 'total' => 832, 'nombre' => 0], 'etablissements' => []])]);

    expect((new Client('test-secret'))->countEstablishments(criteria()))->toBe(832);

    Http::assertSent(fn (Request $request) => (int) $request['nombre'] === 0);
});

it('counts zero when nothing matches', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 404]], 404)]);

    expect((new Client('test-secret'))->countEstablishments(criteria()))->toBe(0);
});

it('counts establishments by the values of a field', function () {
    Http::fake(['api.insee.fr/*' => Http::response([
        'header' => ['statut' => 200, 'total' => 12, 'nombre' => 0],
        'etablissements' => [],
        'facettes' => [['nom' => 'trancheEffectifsUniteLegale', 'comptages' => [['valeur' => '12', 'nombre' => 9], ['valeur' => '21', 'nombre' => 3]]]],
    ])]);

    expect((new Client('test-secret'))->countEstablishmentsBy(criteria(), 'trancheEffectifsUniteLegale'))->toBe(['12' => 9, '21' => 3]);

    Http::assertSent(fn (Request $request) => $request['facette.champ'] === 'trancheEffectifsUniteLegale' && (int) $request['nombre'] === 0);
});

it('adds the dirigeant of natural persons to the pages', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 200, 'curseur' => '*', 'curseurSuivant' => '*'], 'etablissements' => [
        ['siret' => '11111111100011', 'uniteLegale' => ['nomUniteLegale' => 'DUPONT', 'prenomUsuelUniteLegale' => 'MARIE']],
    ]])]);

    $page = (new Client('test-secret'))->searchEstablishmentsLazily(criteria())->current();

    expect($page->etablissements[0]->uniteLegale->dirigeant->nom)->toBe('DUPONT');
});

it('retries on 5xx and network errors but never on 4xx', function () {
    Http::fake(['api.insee.fr/*' => Http::sequence()
        ->push(['header' => ['statut' => 503]], 503)
        ->pushFailedConnection()
        ->push(pageBody('*', '*', ['11111111100011'])),
    ]);

    $pages = iterator_to_array((new Client('test-secret'))->searchEstablishmentsLazily(criteria()), preserve_keys: false);

    expect($pages[0]->etablissements)->toHaveCount(1);
    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(1)->seconds(), Sleep::for(3)->seconds()]);
});

it('does not retry a request the API rejects', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 400, 'message' => 'Erreur de syntaxe dans le paramètre q']], 400)]);

    try {
        (new Client('test-secret'))->countEstablishments(criteria());
        $this->fail('Expected an InseeRequestException.');
    } catch (InseeRequestException $e) {
        expect($e)->toBeInstanceOf(InseeException::class)
            ->and($e->statusCode)->toBe(400)
            ->and($e->getMessage())->toBe('Erreur de syntaxe dans le paramètre q');
    }

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('throws InseeUnavailableException after retries', function () {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => 503, 'message' => 'Service en maintenance']], 503)]);

    try {
        (new Client('test-secret'))->countEstablishments(criteria());
        $this->fail('Expected an InseeUnavailableException.');
    } catch (InseeUnavailableException $e) {
        expect($e->statusCode)->toBe(503)->and($e->getMessage())->toBe('Service en maintenance');
    }

    Http::assertSentCount(4);
    Sleep::assertSequence([Sleep::for(1)->seconds(), Sleep::for(3)->seconds(), Sleep::for(9)->seconds()]);
});

it('throws InseeUnavailableException when the API cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7'));

    (new Client('test-secret'))->countEstablishments(criteria());
})->throws(InseeUnavailableException::class, 'injoignable');

it('returns an establishment, or null for an unknown siret', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/11111111100011' => Http::response(['header' => ['statut' => 200], 'etablissement' => ['siret' => '11111111100011', 'statutDiffusionEtablissement' => 'P']]),
        'api.insee.fr/api-sirene/3.11/siret/99999999900099' => Http::response(['header' => ['statut' => 404, 'message' => 'Aucun élément trouvé']], 404),
    ]);
    $client = new Client('test-secret');

    expect($client->establishmentOrFail('11111111100011')->statutDiffusionEtablissement)->toBe('P')
        ->and($client->establishmentOrFail('99999999900099'))->toBeNull();
});

it('throws typed exceptions from establishmentOrFail', function (int $status, string $exception) {
    Http::fake(['api.insee.fr/*' => Http::response(['header' => ['statut' => $status, 'message' => 'Boom']], $status, $status === 429 ? ['x-quota-remaining' => '0', 'x-quota-reset' => (string) (now()->addMinutes(20)->getTimestamp() * 1000)] : [])]);

    expect(fn () => (new Client('test-secret'))->establishmentOrFail('11111111100011'))->toThrow($exception, 'Boom');
})->with([
    'quota' => [429, InseeQuotaExceededException::class],
    'unavailable' => [500, InseeUnavailableException::class],
    'request' => [403, InseeRequestException::class],
]);
