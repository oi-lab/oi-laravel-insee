<?php

use OiLab\OiLaravelInsee\Enums\WorkforceRange;

it('maps every INSEE workforce code', function () {
    expect(array_map(fn (WorkforceRange $range) => $range->value, WorkforceRange::cases()))
        ->toBe(['NN', '00', '01', '02', '03', '11', '12', '21', '22', '31', '32', '41', '42', '51', '52', '53']);
});

it('exposes the bounds and the label of a range', function () {
    expect(WorkforceRange::FiftyToNinetyNine->min())->toBe(50)
        ->and(WorkforceRange::FiftyToNinetyNine->max())->toBe(99)
        ->and(WorkforceRange::FiftyToNinetyNine->label())->toBe('50 à 99 salariés')
        ->and(WorkforceRange::NoEmployer->min())->toBeNull()
        ->and(WorkforceRange::NoEmployer->max())->toBeNull()
        ->and(WorkforceRange::Zero->max())->toBe(0)
        ->and(WorkforceRange::TenThousandOrMore->max())->toBeNull();
});

it('keeps the ranges entirely inside a headcount interval', function () {
    $codes = fn (?int $min, ?int $max) => array_map(fn (WorkforceRange $range) => $range->value, WorkforceRange::within($min, $max));

    expect($codes(20, 249))->toBe(['12', '21', '22', '31'])
        ->and($codes(null, 9))->toBe(['00', '01', '02', '03'])
        ->and($codes(500, null))->toBe(['41', '42', '51', '52', '53']);
});
