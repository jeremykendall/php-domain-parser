<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\PublicSuffixList;

final class RulesStorage implements PublicSuffixListStorage
{
    private PublicSuffixListCache $cache;

    private PublicSuffixListClient $client;

    public function __construct(PublicSuffixListCache $cache, PublicSuffixListClient $client)
    {
        $this->cache = $cache;
        $this->client = $client;
    }

    public function get(string $uri): PublicSuffixList
    {
        $publicSuffixList = $this->cache->fetch($uri);
        if (null !== $publicSuffixList) {
            return $publicSuffixList;
        }

        $publicSuffixList = $this->client->get($uri);

        $this->cache->remember($uri, $publicSuffixList);

        return $publicSuffixList;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
