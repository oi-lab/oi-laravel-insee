<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * Postal address of an establishment (`adresseEtablissement` / `adresse2Etablissement`).
 */
class AdresseEtablissement extends Data
{
    public function __construct(
        public ?string $complementAdresseEtablissement = null,
        public ?string $numeroVoieEtablissement = null,
        public ?string $indiceRepetitionEtablissement = null,
        public ?string $typeVoieEtablissement = null,
        public ?string $libelleVoieEtablissement = null,
        public ?string $codePostalEtablissement = null,
        public ?string $libelleCommuneEtablissement = null,
        public ?string $libelleCommuneEtrangerEtablissement = null,
        public ?string $distributionSpecialeEtablissement = null,
        public ?string $codeCommuneEtablissement = null,
        public ?string $codeCedexEtablissement = null,
        public ?string $libelleCedexEtablissement = null,
        public ?string $codePaysEtrangerEtablissement = null,
        public ?string $libellePaysEtrangerEtablissement = null,
    ) {}
}
