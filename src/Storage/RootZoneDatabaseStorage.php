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
use Pdp\Storage\Cache\RootZoneDatabaseCache;
use Pdp\Storage\Http\RootZoneDatabaseClient;

class RootZoneDatabaseStorage implements RootZoneDatabaseClient
{
    private RootZoneDatabaseClient $client;

    private RootZoneDatabaseCache $cache;

    public function __construct(RootZoneDatabaseClient $client, RootZoneDatabaseCache $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    public function getByUri(string $uri = self::RZD_URL): RootZoneDatabase
    {
        $rootZoneDatabase = $this->cache->fetchByUri($uri);
        if (null !== $rootZoneDatabase) {
            return $rootZoneDatabase;
        }

        $rootZoneDatabase = $this->client->getByUri($uri);

        $this->cache->storeByUri($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }
}
