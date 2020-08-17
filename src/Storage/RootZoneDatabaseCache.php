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

namespace Pdp\Storage;

use Pdp\RootZoneDatabase;

interface RootZoneDatabaseCache
{
    /**
     * Retrieves the Root Zone Database from the Cache.
     */
    public function fetchByUri(string $uri): ?RootZoneDatabase;

    /**
     * Cache the Root Zone Database.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function storeByUri(string $uri, RootZoneDatabase $topLevelDomains): bool;
}
