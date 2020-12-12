<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Pdp\TopLevelDomainList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use TypeError;
use function filter_var;
use function is_string;
use function md5;
use function strtolower;
use const FILTER_VALIDATE_INT;

final class TopLevelDomainListPsr16Cache implements TopLevelDomainListCache
{
    private CacheInterface $cache;

    private string $cachePrefix;

    private ?DateInterval $cacheTtl;

    /**
     * @param mixed $cacheTtl storage TTL
     */
    public function __construct(CacheInterface $cache, string $cachePrefix = '', $cacheTtl = null)
    {
        $this->cache = $cache;
        $this->cachePrefix = $cachePrefix;
        $this->cacheTtl = $this->setTtl($cacheTtl);
    }

    /**
     * Set the cache TTL.
     *
     * @param mixed $ttl the cache TTL
     *
     * @throws InvalidArgumentException if the value can not be computed
     * @throws TypeError                if the value type is not recognized
     */
    private function setTtl($ttl): ?DateInterval
    {
        if ($ttl instanceof DateInterval || null === $ttl) {
            return $ttl;
        }

        if ($ttl instanceof DateTimeInterface) {
            /** @var DateTimeZone $timezone */
            $timezone = $ttl->getTimezone();

            $now = new DateTimeImmutable('NOW', $timezone);
            /** @var DateInterval $ttl */
            $ttl = $now->diff($ttl, false);

            return $ttl;
        }

        if (false !== ($res = filter_var($ttl, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        if (!is_string($ttl)) {
            throw new TypeError('The ttl must null, an integer, a string, a DateTimeInterface or a DateInterval object.');
        }

        /** @var DateInterval|false $date */
        $date = @DateInterval::createFromDateString($ttl);
        if (!$date instanceof DateInterval) {
            throw new InvalidArgumentException('The ttl value "'.$ttl.'" can not be parsable by `DateInterval::createFromDateString`.');
        }

        return $date;
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
        } catch (CacheException $exception) {
            return false;
        }
    }

    public function forget(string $uri): bool
    {
        return $this->cache->delete($this->cacheKey($uri));
    }
}
