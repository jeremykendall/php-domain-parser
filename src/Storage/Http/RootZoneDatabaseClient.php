<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Storage\Http;

use Pdp\RootZoneDatabase;
use Pdp\UnableToLoadRootZoneDatabase;

interface RootZoneDatabaseClient
{
    public const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * Gets the Top Level Domains from the Local Storage or the Remote Storage.
     *
     * @throws ClientException
     * @throws UnableToLoadRootZoneDatabase
     */
    public function getByUri(string $uri = self::RZD_URL): RootZoneDatabase;
}
