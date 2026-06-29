<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * A SIRENE establishment (`etablissement`), identified by its SIRET.
 */
class Etablissement extends Data
{
    public function __construct(
        public ?string $siren = null,
        public ?string $nic = null,
        public ?string $siret = null,
        public ?string $statutDiffusionEtablissement = null,
        public ?string $dateCreationEtablissement = null,
        public ?string $trancheEffectifsEtablissement = null,
        public ?string $anneeEffectifsEtablissement = null,
        public ?string $activitePrincipaleRegistreMetiersEtablissement = null,
        public ?string $dateDernierTraitementEtablissement = null,
        public ?bool $etablissementSiege = null,
        public ?int $nombrePeriodesEtablissement = null,
        public ?UniteLegale $uniteLegale = null,
        public ?AdresseEtablissement $adresseEtablissement = null,
        public ?AdresseEtablissement $adresse2Etablissement = null,
        /** @var PeriodeEtablissement[] */
        public array $periodesEtablissement = [],
    ) {}
}
