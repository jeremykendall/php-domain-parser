<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;

final class TopLevelDomainsStorage implements TopLevelDomainListStorage
{
    private TopLevelDomainListCache $cache;

    private TopLevelDomainListClient $client;

    public function __construct(TopLevelDomainListCache $cache, TopLevelDomainListClient $client)
    {
        $this->cache = $cache;
        $this->client = $client;
    }

    public function get(string $uri): TopLevelDomainList
    {
        $topLevelDomains = $this->cache->fetch($uri);
        if (null !== $topLevelDomains) {
            return $topLevelDomains;
        }

        $topLevelDomains = $this->client->get($uri);

        $this->cache->remember($uri, $topLevelDomains);

        return $topLevelDomains;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
