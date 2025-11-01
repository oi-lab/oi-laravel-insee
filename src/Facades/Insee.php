<?php

namespace OiLab\Insee\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array findSiret(string $siret)
 * @method static array findSiren(string $siren)
 * @method static array searchCompanies(array $params)
 * @method static array searchEstablishments(array $params)
 * @method static array getApiStatus()
 *
 * @see \OiLab\Insee\Client
 */
class Insee extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'insee';
    }
}
