<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Response of the `/siren` search endpoint (a list of legal units).
 */
class SirenSearchResponse extends Data
{
    public function __construct(
        public ?ResponseHeader $header = null,
        /** @var UniteLegale[] */
        public array $unitesLegales = [],
    ) {}
}
