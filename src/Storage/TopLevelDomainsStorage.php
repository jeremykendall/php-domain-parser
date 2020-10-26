<?php

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

        $this->cache->remember($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
