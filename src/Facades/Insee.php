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
 * @method static \Generator<int, \OiLab\OiLaravelInsee\Data\SiretSearchPage> searchEstablishmentsLazily(\OiLab\OiLaravelInsee\Search\SiretSearchCriteria $criteria, ?string $cursor = null, int $pageSize = 1000)
 * @method static int countEstablishments(\OiLab\OiLaravelInsee\Search\SiretSearchCriteria $criteria)
 * @method static array<string, int> countEstablishmentsBy(\OiLab\OiLaravelInsee\Search\SiretSearchCriteria $criteria, string $field)
 * @method static \OiLab\OiLaravelInsee\Data\Etablissement|null establishmentOrFail(string $siret)
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
