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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

final class PsrStorageFactory implements
    RemoteStorageURL,
    PublicSuffixListCacheFactory,
    PublicSuffixListClientFactory,
    PublicSuffixListStorageFactory,
    RootZoneDatabaseCacheFactory,
    RootZoneDatabaseClientFactory,
    RootZoneDatabaseStorageFactory
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private CacheInterface $cache;

    private ?LoggerInterface $logger;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        CacheInterface $cache,
        LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function createPublicSuffixListClient(): PublicSuffixListRepository
    {
        return new RulesPsr18Client($this->client, $this->requestFactory);
    }

    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createPublicSuffixListCache(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListCache
    {
        return new RulesPsr16Cache(
            new JsonSerializablePsr16Cache($this->cache, $cachePrefix, $cacheTtl, $this->logger)
        );
    }

    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage
    {
        return new RulesRepository(
            $this->createPublicSuffixListClient(),
            $this->createPublicSuffixListCache($cachePrefix, $cacheTtl)
        );
    }

    public function createRootZoneDatabaseClient(): RootZoneDatabaseRepository
    {
        return new TopLevelDomainsPsr18Client($this->client, $this->requestFactory);
    }

    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createRootZoneDatabaseCache(string $cachePrefix = '', $cacheTtl = null): RootZoneDatabaseCache
    {
        return new TopLevelDomainsPsr16Cache(
            new JsonSerializablePsr16Cache($this->cache, $cachePrefix, $cacheTtl, $this->logger)
        );
    }

    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createRootZoneDatabaseStorage(string $cachePrefix = '', $cacheTtl = null): RootZoneDatabaseStorage
    {
        return new TopLevelDomainsRepository(
            $this->createRootZoneDatabaseClient(),
            $this->createRootZoneDatabaseCache($cachePrefix, $cacheTtl)
        );
    }
}
