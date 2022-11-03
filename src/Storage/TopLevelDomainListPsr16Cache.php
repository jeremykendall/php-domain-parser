<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Pdp\TopLevelDomainList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Stringable;
use Throwable;
use function md5;
use function strtolower;

final class TopLevelDomainListPsr16Cache implements TopLevelDomainListCache
{
    private readonly ?DateInterval $cacheTtl;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ) {
        $this->cacheTtl = TimeToLive::convert($cacheTtl);
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
