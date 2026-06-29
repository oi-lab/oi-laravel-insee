<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * A SIRENE legal unit (`uniteLegale`).
 *
 * Static identity fields live on the unit itself, while time-varying values are
 * exposed through `periodesUniteLegale`. The `dirigeant` key is injected by the
 * Client when the unit is a natural person.
 */
class UniteLegale extends Data
{
    public function __construct(
        public ?string $siren = null,
        public ?string $statutDiffusionUniteLegale = null,
        public ?string $dateCreationUniteLegale = null,
        public ?string $sigleUniteLegale = null,
        public ?string $sexeUniteLegale = null,
        public ?string $prenom1UniteLegale = null,
        public ?string $prenom2UniteLegale = null,
        public ?string $prenom3UniteLegale = null,
        public ?string $prenom4UniteLegale = null,
        public ?string $prenomUsuelUniteLegale = null,
        public ?string $pseudonymeUniteLegale = null,
        public ?string $identifiantAssociationUniteLegale = null,
        public ?string $trancheEffectifsUniteLegale = null,
        public ?string $anneeEffectifsUniteLegale = null,
        public ?string $dateDernierTraitementUniteLegale = null,
        public ?int $nombrePeriodesUniteLegale = null,
        public ?string $categorieEntreprise = null,
        public ?string $anneeCategorieEntreprise = null,
        public ?string $denominationUniteLegale = null,
        public ?string $nomUniteLegale = null,
        public ?string $nomUsageUniteLegale = null,
        public ?string $categorieJuridiqueUniteLegale = null,
        public ?string $activitePrincipaleUniteLegale = null,
        public ?string $etatAdministratifUniteLegale = null,
        /** @var PeriodeUniteLegale[] */
        public array $periodesUniteLegale = [],
        public ?Dirigeant $dirigeant = null,
    ) {}
}
