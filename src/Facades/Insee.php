<?php

namespace OiLab\OiLaravelInsee\Facades;

use Illuminate\Support\Facades\Facade;
use OiLab\OiLaravelInsee\Client;

/**
 * @method static array findSiret(string $siret)
 * @method static array findSiren(string $siren)
 * @method static array searchCompanies(array $params)
 * @method static array searchEstablishments(array $params)
 * @method static array getApiStatus()
 * @method static \OiLab\OiLaravelInsee\Data\SiretResponse siret(string $siret)
 * @method static \OiLab\OiLaravelInsee\Data\SirenResponse siren(string $siren)
 * @method static \OiLab\OiLaravelInsee\Data\SirenSearchResponse companies(array $params)
 * @method static \OiLab\OiLaravelInsee\Data\SiretSearchResponse establishments(array $params)
 *
 * @see Client
 */
class Insee extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'insee';
    }
}
