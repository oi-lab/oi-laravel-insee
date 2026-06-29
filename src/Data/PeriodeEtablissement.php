<?php

namespace OiLab\OiLaravelInsee\Data;

use Spatie\LaravelData\Data;

/**
 * A time-bounded period of an establishment (entry of `periodesEtablissement`).
 */
class PeriodeEtablissement extends Data
{
    public function __construct(
        public ?string $dateFin = null,
        public ?string $dateDebut = null,
        public ?string $etatAdministratifEtablissement = null,
        public ?bool $changementEtatAdministratifEtablissement = null,
        public ?string $enseigne1Etablissement = null,
        public ?string $enseigne2Etablissement = null,
        public ?string $enseigne3Etablissement = null,
        public ?bool $changementEnseigneEtablissement = null,
        public ?string $denominationUsuelleEtablissement = null,
        public ?bool $changementDenominationUsuelleEtablissement = null,
        public ?string $activitePrincipaleEtablissement = null,
        public ?string $nomenclatureActivitePrincipaleEtablissement = null,
        public ?bool $changementActivitePrincipaleEtablissement = null,
        public ?string $caractereEmployeurEtablissement = null,
        public ?bool $changementCaractereEmployeurEtablissement = null,
    ) {}
}
