<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Pdp\PublicSuffixList;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use TypeError;
use function filter_var;
use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function md5;
use function sprintf;
use function strtolower;
use const FILTER_VALIDATE_INT;

final class PublicSuffixListPsr16Cache implements PublicSuffixListCache
{
    private CacheInterface $cache;

    private string $cachePrefix;

    private ?DateInterval $cacheTtl;

    /**
     * @param mixed $cacheTtl cache TTL
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
     * @throws TypeError if the value type is not recognized
     */
    private function setTtl($ttl): ?DateInterval
    {
        if ($ttl instanceof DateInterval || null === $ttl) {
            return $ttl;
        }

        if ($ttl instanceof DateTimeInterface) {
            return (new DateTimeImmutable('NOW', $ttl->getTimezone()))->diff($ttl);
        }

        if (false !== ($res = filter_var($ttl, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        if (!is_string($ttl)) {
            throw new TypeError(sprintf(
                'The ttl must null, an integer, a string a DateTimeInterface or a DateInterval object %s given.',
                is_object($ttl) ? get_class($ttl) : gettype($ttl)
            ));
        }

        /** @var DateInterval|false $date */
        $date = @DateInterval::createFromDateString($ttl);
        if (!$date instanceof DateInterval) {
            throw new InvalidArgumentException(sprintf(
                'The ttl value "%s" can not be parsable by `DateInterval::createFromDateString`.',
                $ttl
            ));
        }

        return $date;
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
        } catch (CacheException $exception) {
            return false;
        }
    }

    public function forget(string $uri): bool
    {
        return $this->cache->delete($this->cacheKey($uri));
    }
}
