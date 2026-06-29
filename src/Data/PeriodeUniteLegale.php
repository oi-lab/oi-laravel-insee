<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * A time-bounded period of a legal unit (entry of `periodesUniteLegale`).
 */
class PeriodeUniteLegale extends Data
{
    public function __construct(
        public ?string $dateFin = null,
        public ?string $dateDebut = null,
        public ?string $etatAdministratifUniteLegale = null,
        public ?bool $changementEtatAdministratifUniteLegale = null,
        public ?string $nomUniteLegale = null,
        public ?bool $changementNomUniteLegale = null,
        public ?string $nomUsageUniteLegale = null,
        public ?bool $changementNomUsageUniteLegale = null,
        public ?string $denominationUniteLegale = null,
        public ?bool $changementDenominationUniteLegale = null,
        public ?string $denominationUsuelle1UniteLegale = null,
        public ?string $denominationUsuelle2UniteLegale = null,
        public ?string $denominationUsuelle3UniteLegale = null,
        public ?bool $changementDenominationUsuelleUniteLegale = null,
        public ?string $categorieJuridiqueUniteLegale = null,
        public ?bool $changementCategorieJuridiqueUniteLegale = null,
        public ?string $activitePrincipaleUniteLegale = null,
        public ?string $nomenclatureActivitePrincipaleUniteLegale = null,
        public ?bool $changementActivitePrincipaleUniteLegale = null,
        public ?string $nicSiegeUniteLegale = null,
        public ?bool $changementNicSiegeUniteLegale = null,
        public ?string $economieSocialeSolidaireUniteLegale = null,
        public ?bool $changementEconomieSocialeSolidaireUniteLegale = null,
        public ?string $societeMissionUniteLegale = null,
        public ?bool $changementSocieteMissionUniteLegale = null,
        public ?string $caractereEmployeurUniteLegale = null,
        public ?bool $changementCaractereEmployeurUniteLegale = null,
    ) {}
}
