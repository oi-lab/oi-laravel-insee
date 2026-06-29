<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Response of the `/siret` search endpoint (a list of establishments).
 */
class SiretSearchResponse extends Data
{
    public function __construct(
        public ?ResponseHeader $header = null,
        /** @var Etablissement[] */
        public array $etablissements = [],
    ) {}
}
