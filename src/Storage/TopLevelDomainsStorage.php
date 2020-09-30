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
    private RootZoneDatabaseClient $repository;

    private RootZoneDatabaseCache $cache;

    public function __construct(RootZoneDatabaseClient $repository, RootZoneDatabaseCache $cache)
    {
        $this->repository = $repository;
        $this->cache = $cache;
    }

    public function get(string $uri): RootZoneDatabase
    {
        $rootZoneDatabase = $this->cache->fetch($uri);
        if (null !== $rootZoneDatabase) {
            return $rootZoneDatabase;
        }

        $rootZoneDatabase = $this->repository->get($uri);

        $this->cache->store($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->delete($uri);
    }
}
