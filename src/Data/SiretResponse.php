<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Response of the `/siret/{siret}` endpoint (a single establishment).
 */
class SiretResponse extends Data
{
    public function __construct(
        public ?ResponseHeader $header = null,
        public ?Etablissement $etablissement = null,
    ) {}
}
