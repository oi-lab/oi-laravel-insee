<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

class ResponseHeader extends Data
{
    public function __construct(
        public ?int $statut = null,
        public ?string $message = null,
        public ?int $total = null,
        public ?int $debut = null,
        public ?int $nombre = null,
        public ?string $curseur = null,
        public ?string $curseurSuivant = null,
    ) {}
}
