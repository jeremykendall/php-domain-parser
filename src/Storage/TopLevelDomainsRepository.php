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
use Pdp\UnableToLoadRootZoneDatabase;

final class TopLevelDomainsRepository implements RootZoneDatabaseStorage
{
    private RootZoneDatabaseRepository $repository;

    private RootZoneDatabaseCache $cache;

    public function __construct(RootZoneDatabaseRepository $repository, RootZoneDatabaseCache $cache)
    {
        $this->repository = $repository;
        $this->cache = $cache;
    }

    public function getByUri(string $uri): RootZoneDatabase
    {
        $rootZoneDatabase = $this->cache->fetchByUri($uri);
        if (null !== $rootZoneDatabase) {
            return $rootZoneDatabase;
        }

        $rootZoneDatabase = $this->repository->getByUri($uri);

        $this->cache->storeByUri($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }

    public function saveByUri(string $uri): void
    {
        $rootZoneDatabase = $this->repository->getByUri($uri);

        if (!$this->cache->storeByUri($uri, $rootZoneDatabase)) {
            throw  UnableToLoadRootZoneDatabase::dueToCachingIssues();
        }
    }
}
