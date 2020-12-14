<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use InvalidArgumentException;
use Pdp\TopLevelDomainList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use function md5;
use function strtolower;

final class TopLevelDomainListPsr16Cache implements TopLevelDomainListCache
{
    private CacheInterface $cache;

    private string $cachePrefix;

    private ?DateInterval $cacheTtl;

    /**
     * @param DateInterval|DateTimeInterface|object|int|string|null $cacheTtl storage TTL object should implement the __toString method
     */
    public function __construct(CacheInterface $cache, string $cachePrefix = '', $cacheTtl = null)
    {
        $this->cache = $cache;
        $this->cachePrefix = $cachePrefix;
        $this->cacheTtl = $this->setCacheTtl($cacheTtl);
    }

    /**
     * @param DateInterval|DateTimeInterface|object|int|string|null $cacheTtl storage TTL object should implement the __toString method
     *
     * @throws InvalidArgumentException if the value can not be computed
     */
    private function setCacheTtl($cacheTtl): ?DateInterval
    {
        if ($cacheTtl instanceof DateInterval || null === $cacheTtl) {
            return $cacheTtl;
        }

        if ($cacheTtl instanceof DateTimeInterface) {
            return TimeToLive::fromNow($cacheTtl);
        }

        return TimeToLive::fromDurationString($cacheTtl);
    }

    public function fetch(string $uri): ?TopLevelDomainList
    {
        $cacheKey = $this->cacheKey($uri);
        $topLevelDomainList = $this->cache->get($cacheKey);
        if (null === $topLevelDomainList) {
            return null;
        }

        if (!$topLevelDomainList instanceof TopLevelDomainList) {
            $this->cache->delete($cacheKey);

            return null;
        }

        return $topLevelDomainList;
    }

    /**
     * Returns the cache key according to the source URL.
     */
    private function cacheKey(string $str): string
    {
        return $this->cachePrefix.md5(strtolower($str));
    }

    public function remember(string $uri, TopLevelDomainList $topLevelDomainList): bool
    {
        try {
            return $this->cache->set($this->cacheKey($uri), $topLevelDomainList, $this->cacheTtl);
        } catch (Throwable $exception) {
            if ($exception instanceof CacheException) {
                return false;
            }

            throw $exception;
        }
    }

    public function forget(string $uri): bool
    {
        return $this->cache->delete($this->cacheKey($uri));
    }
}
