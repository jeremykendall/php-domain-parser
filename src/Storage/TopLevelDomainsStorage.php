<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;

final class TopLevelDomainsStorage implements TopLevelDomainListStorage
{
    public function __construct(
        private readonly TopLevelDomainListCache $cache,
        private readonly TopLevelDomainListClient $client
    ) {
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
