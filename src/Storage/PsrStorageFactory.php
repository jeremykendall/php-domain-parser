<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Pdp\ResourceUri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Stringable;

final class PsrStorageFactory implements
    ResourceUri,
    PublicSuffixListStorageFactory,
    TopLevelDomainListStorageFactory
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory
    ) {
    }

    /**
     * @param DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage
    {
        return new RulesStorage(
            new PublicSuffixListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new PublicSuffixListPsr18Client($this->client, $this->requestFactory)
        );
    }

    /**
     * @param DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl
     */
    public function createTopLevelDomainListStorage(string $cachePrefix = '', $cacheTtl = null): TopLevelDomainListStorage
    {
        return new TopLevelDomainsStorage(
            new TopLevelDomainListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new TopLevelDomainListPsr18Client($this->client, $this->requestFactory)
        );
    }
}
