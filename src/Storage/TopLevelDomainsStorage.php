<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\RootZoneDatabase;

final class TopLevelDomainsStorage implements RootZoneDatabaseStorage
{
    private RootZoneDatabaseCache $cache;

    private RootZoneDatabaseClient $client;

    public function __construct(RootZoneDatabaseCache $cache, RootZoneDatabaseClient $client)
    {
        $this->cache = $cache;
        $this->client = $client;
    }

    public function get(string $uri): RootZoneDatabase
    {
        $rootZoneDatabase = $this->cache->fetch($uri);
        if (null !== $rootZoneDatabase) {
            return $rootZoneDatabase;
        }

        $rootZoneDatabase = $this->client->get($uri);

        $this->cache->store($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
