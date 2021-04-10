<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Pdp\PublicSuffixList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use function md5;
use function strtolower;

final class PublicSuffixListPsr16Cache implements PublicSuffixListCache
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
        $this->cacheTtl = TimeToLive::convert($cacheTtl);
    }

    public function fetch(string $uri): ?PublicSuffixList
    {
        $cacheKey = $this->cacheKey($uri);
        $publicSuffixList = $this->cache->get($cacheKey);
        if (null === $publicSuffixList) {
            return null;
        }

        if (!$publicSuffixList instanceof PublicSuffixList) {
            $this->cache->delete($cacheKey);

            return null;
        }

        return $publicSuffixList;
    }

    /**
     * Returns the cache key according to the source URL.
     */
    private function cacheKey(string $str): string
    {
        return $this->cachePrefix.md5(strtolower($str));
    }

    public function remember(string $uri, PublicSuffixList $publicSuffixList): bool
    {
        try {
            return $this->cache->set($this->cacheKey($uri), $publicSuffixList, $this->cacheTtl);
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
