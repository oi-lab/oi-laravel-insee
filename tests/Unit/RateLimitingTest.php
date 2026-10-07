<?php

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use OiLab\OiLaravelInsee\Client;
use OiLab\OiLaravelInsee\Exceptions\InseeQuotaExceededException;
use OiLab\OiLaravelInsee\Search\SiretSearchCriteria;

function countCriteria(): SiretSearchCriteria
{
    return SiretSearchCriteria::make()->communeCodes(['34172']);
}

function countResponse(array $headers = [], int $status = 200): Illuminate\Http\Client\PromiseInterface|PromiseInterface
{
    return Http::response(['header' => ['statut' => $status, 'total' => 7]], $status, $headers);
}

function epochMs(string $when): string
{
    return (string) (CarbonImmutable::parse($when)->getTimestamp() * 1000);
}

it('throws InseeQuotaExceededException on 429 with the retry date', function () {
    Http::fake(['api.insee.fr/*' => countResponse(['x-quota-remaining' => '0', 'x-quota-reset' => epochMs('2026-10-07 10:20:00')], 429)]);
    $client = new Client('test-secret');

    try {
        $client->countEstablishments(countCriteria());
        $this->fail('Expected an InseeQuotaExceededException.');
    } catch (InseeQuotaExceededException $e) {
        expect($e->statusCode)->toBe(429)
            ->and($e->retryAt->toIso8601String())->toBe('2026-10-07T10:20:00+00:00');
    }

    // The exhausted quota is remembered: the next call does not even reach the API.
    expect(fn () => $client->countEstablishments(countCriteria()))->toThrow(InseeQuotaExceededException::class);
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('gives the end of the minute as retry date when only the minute window is full', function () {
    Http::fake(['api.insee.fr/*' => countResponse(['x-quota-remaining' => '1500', 'x-rate-limit-remaining' => '0', 'x-rate-limit-reset' => epochMs('2026-10-07 10:00:40')], 429)]);

    try {
        (new Client('test-secret'))->countEstablishments(countCriteria());
        $this->fail('Expected an InseeQuotaExceededException.');
    } catch (InseeQuotaExceededException $e) {
        expect($e->retryAt->toIso8601String())->toBe('2026-10-07T10:00:40+00:00');
    }
});

it('throws when the hourly window is exhausted', function () {
    config()->set('oi-laravel-insee.rate_limits.per_hour', 3);
    config()->set('oi-laravel-insee.rate_limits.background_ceiling', 3);
    Http::fake(['api.insee.fr/*' => countResponse()]);
    $client = new Client('test-secret');

    foreach (range(1, 3) as $ignored) {
        $client->countEstablishments(countCriteria());
    }

    try {
        $client->countEstablishments(countCriteria());
        $this->fail('Expected an InseeQuotaExceededException.');
    } catch (InseeQuotaExceededException $e) {
        expect($e->retryAt->toIso8601String())->toBe('2026-10-07T11:00:00+00:00');
    }

    Http::assertSentCount(3);
});

it('waits when the minute window is full', function () {
    config()->set('oi-laravel-insee.rate_limits.per_minute', 2);
    Http::fake(['api.insee.fr/*' => countResponse()]);
    $client = new Client('test-secret');

    foreach (range(1, 3) as $ignored) {
        $client->countEstablishments(countCriteria());
    }

    Http::assertSentCount(3);
    Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 60, times: 1);
});

it('throws instead of sleeping beyond the longest accepted wait', function () {
    config()->set('oi-laravel-insee.rate_limits.per_minute', 1);
    config()->set('oi-laravel-insee.rate_limits.max_wait_seconds', 10);
    Http::fake(['api.insee.fr/*' => countResponse()]);
    $client = new Client('test-secret');
    $client->countEstablishments(countCriteria());

    expect(fn () => $client->countEstablishments(countCriteria()))->toThrow(InseeQuotaExceededException::class);
    Sleep::assertNeverSlept();
});

it('waits for the minute reset announced by the headers', function () {
    Http::fake(['api.insee.fr/*' => Http::sequence()
        ->push(['header' => ['statut' => 200, 'total' => 1]], 200, ['x-quota-remaining' => '1900', 'x-rate-limit-remaining' => '0', 'x-rate-limit-reset' => epochMs('2026-10-07 10:00:20')])
        ->push(['header' => ['statut' => 200, 'total' => 1]]),
    ]);
    $client = new Client('test-secret');

    $client->countEstablishments(countCriteria());
    $client->countEstablishments(countCriteria());

    Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 20, times: 1);
    Http::assertSentCount(2);
});

it('stops background searches at the background ceiling while unit calls still pass', function () {
    config()->set('oi-laravel-insee.rate_limits.per_hour', 10);
    config()->set('oi-laravel-insee.rate_limits.background_ceiling', 3);
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/*' => Http::response(['header' => ['statut' => 200], 'etablissement' => ['siret' => '11111111100011']]),
        'api.insee.fr/*' => countResponse(),
    ]);
    $client = new Client('test-secret');

    foreach (range(1, 3) as $ignored) {
        $client->countEstablishments(countCriteria());
    }

    expect(fn () => $client->countEstablishments(countCriteria()))->toThrow(InseeQuotaExceededException::class, 'plafond')
        ->and(fn () => iterator_to_array($client->searchEstablishmentsLazily(countCriteria())))->toThrow(InseeQuotaExceededException::class)
        ->and($client->establishmentOrFail('11111111100011')->siret)->toBe('11111111100011');
});

it('applies the background ceiling to the remainder announced by the headers', function () {
    Http::fake([
        'api.insee.fr/api-sirene/3.11/siret/*' => Http::response(['header' => ['statut' => 200], 'etablissement' => ['siret' => '11111111100011']]),
        'api.insee.fr/*' => countResponse(['x-quota-limit' => '2000', 'x-quota-remaining' => '399', 'x-quota-reset' => epochMs('2026-10-07 10:30:00')]),
    ]);
    $client = new Client('test-secret');
    $client->countEstablishments(countCriteria());

    try {
        $client->countEstablishments(countCriteria());
        $this->fail('Expected an InseeQuotaExceededException.');
    } catch (InseeQuotaExceededException $e) {
        expect($e->retryAt->toIso8601String())->toBe('2026-10-07T10:30:00+00:00');
    }

    expect($client->establishmentOrFail('11111111100011')->siret)->toBe('11111111100011');
});

it('stops every call, unit ones included, once the quota is used up', function () {
    Http::fake(['api.insee.fr/*' => countResponse(['x-quota-remaining' => '0', 'x-quota-reset' => epochMs('2026-10-07 10:45:00')])]);
    $client = new Client('test-secret');
    $client->countEstablishments(countCriteria());

    expect(fn () => $client->establishmentOrFail('11111111100011'))->toThrow(InseeQuotaExceededException::class);
    Http::assertSentCount(1);
});

it('shares the budget between clients through the cache', function () {
    config()->set('oi-laravel-insee.rate_limits.per_hour', 2);
    config()->set('oi-laravel-insee.rate_limits.background_ceiling', 2);
    Http::fake(['api.insee.fr/*' => countResponse()]);

    (new Client('test-secret'))->countEstablishments(countCriteria());
    (new Client('test-secret'))->countEstablishments(countCriteria());

    expect(fn () => (new Client('test-secret'))->countEstablishments(countCriteria()))->toThrow(InseeQuotaExceededException::class);
});
