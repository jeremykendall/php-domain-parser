<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Pdp\PublicSuffixList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Stringable;
use Throwable;
use function md5;
use function strtolower;

final class PublicSuffixListPsr16Cache implements PublicSuffixListCache
{
    private readonly ?DateInterval $cacheTtl;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ) {
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
