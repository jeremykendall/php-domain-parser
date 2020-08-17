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

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Throwable;
use TypeError;
use function filter_var;
use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function json_encode;
use function md5;
use function sprintf;
use function strtolower;
use const FILTER_VALIDATE_INT;

abstract class Psr16JsonCache
{
    protected const CACHE_PREFIX = 'pdp_';

    protected CacheInterface $cache;

    protected LoggerInterface $logger;

    protected ?DateInterval $ttl;

    /**
     * @param mixed $ttl the time to live for the given cache
     */
    public function __construct(CacheInterface $cache, $ttl = null, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->ttl = $this->setTtl($ttl);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set the cache TTL.
     *
     * @param mixed $ttl the cache TTL
     *
     * @throws TypeError if the value type is not recognized
     */
    final protected function setTtl($ttl): ?DateInterval
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

    final protected function store(string $uri, JsonSerializable $object): bool
    {
        try {
            $result = $this->cache->set($this->cacheKey($uri), json_encode($object), $this->ttl);
        } catch (Throwable $exception) {
            $this->logger->info(
                'The content associated with URI: `'.$uri.'` could not be cached: '.$exception->getMessage(),
                ['exception' => $exception]
            );

            return false;
        }

        $message = 'The content associated with URI: `'.$uri.'` was stored.';
        if (!$result) {
            $message = 'The content associated with URI: `'.$uri.'` could not be stored.';
        }

        $this->logger->info($message);

        return $result;
    }

    /**
     * Returns the cache key according to the source URL.
     */
    private function cacheKey(string $str): string
    {
        return self::CACHE_PREFIX.md5(strtolower($str));
    }

    final protected function fetch(string $uri): ?string
    {
        return $this->cache->get($this->cacheKey($uri));
    }

    final protected function forget(string $uri, Throwable $exception = null): bool
    {
        $result = $this->cache->delete($this->cacheKey($uri));
        if (null !== $exception) {
            $this->logger->warning($exception->getMessage());
        }

        return $result;
    }
}
