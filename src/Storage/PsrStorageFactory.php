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
        private CacheInterface $cache,
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory
    ) {
    }

    public function createPublicSuffixListStorage(
        string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ): PublicSuffixListStorage {
        return new RulesStorage(
            new PublicSuffixListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new PublicSuffixListPsr18Client($this->client, $this->requestFactory)
        );
    }

    public function createTopLevelDomainListStorage(
        string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ): TopLevelDomainListStorage {
        return new TopLevelDomainsStorage(
            new TopLevelDomainListPsr16Cache($this->cache, $cachePrefix, $cacheTtl),
            new TopLevelDomainListPsr18Client($this->client, $this->requestFactory)
        );
    }
}
