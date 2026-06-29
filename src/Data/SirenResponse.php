<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Response of the `/siren/{siren}` endpoint (a single legal unit).
 */
class SirenResponse extends Data
{
    public function __construct(
        public ?ResponseHeader $header = null,
        public ?UniteLegale $uniteLegale = null,
    ) {}
}
