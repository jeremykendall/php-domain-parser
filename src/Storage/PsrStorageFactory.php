<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class PsrStorageFactory implements
    RemoteStorageURL,
    PublicSuffixListStorageFactory,
    RootZoneDatabaseStorageFactory
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
     * @param mixed $cacheTtl The cache TTL
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage
    {
        return new RulesStorage(
            new PublicSuffixListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new PublicSuffixListPsr18Client($this->client, $this->requestFactory)
        );
    }

    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createRootZoneDatabaseStorage(string $cachePrefix = '', $cacheTtl = null): RootZoneDatabaseStorage
    {
        return new TopLevelDomainsStorage(
            new RootZoneDatabasePsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new RootZoneDatabasePsr18Client($this->client, $this->requestFactory)
        );
    }
}
