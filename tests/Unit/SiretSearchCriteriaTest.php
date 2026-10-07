<?php

use OiLab\OiLaravelInsee\Enums\WorkforceRange;
use OiLab\OiLaravelInsee\Search\SiretSearchCriteria;

it('builds q from typed criteria', function () {
    $query = SiretSearchCriteria::make()
        ->headquartersOnly()
        ->activeOnly()
        ->publicDiffusionOnly()
        ->workforceRanges([WorkforceRange::TwentyToFortyNine, '21'])
        ->communeCodes(['34172', '2a004'])
        ->postalCodes(['34000'])
        ->departmentCodes(['34', '2B', '971'])
        ->nafCodes(['62.01Z'])
        ->toQuery();

    expect($query)->toBe(
        'etablissementSiege:true'
        .' AND periode(etatAdministratifEtablissement:A AND -dateFin:*)'
        .' AND statutDiffusionEtablissement:O'
        .' AND (trancheEffectifsUniteLegale:12 OR trancheEffectifsUniteLegale:21)'
        .' AND (codeCommuneEtablissement:34172 OR codeCommuneEtablissement:2A004 OR codePostalEtablissement:34000'
        .' OR codeCommuneEtablissement:34* OR codeCommuneEtablissement:2B* OR codeCommuneEtablissement:971*)'
        .' AND periode(activitePrincipaleEtablissement:62.01Z AND -dateFin:*)'
    );
});

it('does not parenthesise a single alternative', function () {
    expect(SiretSearchCriteria::make()->workforceRanges(['12'])->communeCodes(['34172'])->toQuery())
        ->toBe('trancheEffectifsUniteLegale:12 AND codeCommuneEtablissement:34172');
});

it('is empty without any criterion', function () {
    expect(SiretSearchCriteria::make()->toQuery())->toBe('');
});

it('groups more than 1000 connectors in parenthesised chunks', function () {
    $communes = array_map(fn (int $i) => sprintf('%05d', 10000 + $i), range(1, 2500));

    $query = SiretSearchCriteria::make()->headquartersOnly()->communeCodes($communes)->toQuery();

    preg_match_all('/\(([^()]*)\)/', $query, $groups);

    expect($query)->toStartWith('etablissementSiege:true AND ((codeCommuneEtablissement:10001 OR ')
        ->and($groups[1])->toHaveCount(3)
        ->and(array_map(fn (string $group) => substr_count($group, ' OR ') + 1, $groups[1]))->toBe([1000, 1000, 500])
        ->and(substr_count($query, 'codeCommuneEtablissement:'))->toBe(2500);
});

it('returns a modified copy instead of mutating the criteria', function () {
    $base = SiretSearchCriteria::make()->headquartersOnly();
    $narrowed = $base->communeCodes(['34172']);

    expect($base->toQuery())->toBe('etablissementSiege:true')
        ->and($narrowed->toQuery())->toBe('etablissementSiege:true AND codeCommuneEtablissement:34172');
});

it('rejects codes that are not codes', function (string $method, string $value) {
    SiretSearchCriteria::make()->{$method}([$value]);
})->throws(InvalidArgumentException::class)->with([
    'commune label' => ['communeCodes', 'Montpellier'],
    'short commune' => ['communeCodes', '3417'],
    'postal code' => ['postalCodes', '3400'],
    'department' => ['departmentCodes', '20'],
    'naf' => ['nafCodes', '6201Z'],
    'workforce' => ['workforceRanges', '99'],
]);

it('accepts Corsican and overseas codes', function () {
    expect(SiretSearchCriteria::make()->departmentCodes(['2a', '2B', '971', '976', '01'])->toQuery())
        ->toBe('(codeCommuneEtablissement:2A* OR codeCommuneEtablissement:2B* OR codeCommuneEtablissement:971* OR codeCommuneEtablissement:976* OR codeCommuneEtablissement:01*)');
});

it('keeps the fields to return', function () {
    expect(SiretSearchCriteria::make()->fields(['siret', 'siren', 'siret'])->selectedFields())->toBe(['siret', 'siren']);
});
