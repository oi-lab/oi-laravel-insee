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
