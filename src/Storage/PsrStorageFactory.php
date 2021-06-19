<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Pdp\ResourceUri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class PsrStorageFactory implements
    ResourceUri,
    PublicSuffixListStorageFactory,
    TopLevelDomainListStorageFactory
{
    private CacheInterface $cache;

    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    public function __construct(CacheInterface $cache, ClientInterface $client, RequestFactoryInterface $requestFactory)
    {
        $this->cache = $cache;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @param DateInterval|DateTimeInterface|object|int|string|null $cacheTtl storage TTL object should implement the __toString method
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage
    {
        return new RulesStorage(
            new PublicSuffixListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new PublicSuffixListPsr18Client($this->client, $this->requestFactory)
        );
    }

    /**
     * @param DateInterval|DateTimeInterface|object|int|string|null $cacheTtl storage TTL object should implement the __toString method
     */
    public function createTopLevelDomainListStorage(string $cachePrefix = '', $cacheTtl = null): TopLevelDomainListStorage
    {
        return new TopLevelDomainsStorage(
            new TopLevelDomainListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new TopLevelDomainListPsr18Client($this->client, $this->requestFactory)
        );
    }
}
