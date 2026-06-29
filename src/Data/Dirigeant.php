<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Dirigeant (natural person) extracted by the Client for entrepreneurs
 * individuels, micro-entrepreneurs and EIRL.
 */
class Dirigeant extends Data
{
    public function __construct(
        public string $nom,
        public ?string $nomUsage = null,
        public ?string $prenom = null,
        public ?string $sexe = null,
    ) {}
}
