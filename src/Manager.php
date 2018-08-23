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

namespace Pdp;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Pdp\Exception\CouldNotLoadRules;
use Pdp\Exception\CouldNotLoadTLDs;
use Psr\SimpleCache\CacheInterface;
use TypeError;
use const FILTER_VALIDATE_INT;
use const JSON_ERROR_NONE;
use function filter_var;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function md5;
use function sprintf;
use function strtolower;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns PHP representations
 * of the Public Suffix List ICANN section
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Manager
{
    const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var HttpClient
     */
    private $http;

    /**
     * @var DateInterval|null
     */
    private $ttl;

    /**
     * @var Converter;
     */
    private $converter;

    /**
     * new instance.
     *
     * @param null|mixed $ttl
     */
    public function __construct(CacheInterface $cache, HttpClient $http, $ttl = null)
    {
        $this->cache = $cache;
        $this->http = $http;
        $this->ttl = $this->setTtl($ttl);
        $this->converter = new Converter();
    }

    /**
     * set the cache TTL.
     *
     * @return DateInterval|null
     */
    private function setTtl($ttl)
    {
        if ($ttl instanceof DateInterval || null === $ttl) {
            return $ttl;
        }

        if ($ttl instanceof DateTimeInterface) {
            return (new DateTimeImmutable('now', $ttl->getTimezone()))->diff($ttl);
        }

        if (false !== ($res = filter_var($ttl, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        if (is_string($ttl)) {
            return DateInterval::createFromDateString($ttl);
        }

        throw new TypeError(sprintf(
            'The ttl must an integer, a string or a DateInterval object %s given',
            is_object($ttl) ? get_class($ttl) : gettype($ttl)
        ));
    }

    /**
     * Gets the Public Suffix List Rules.
     *
     * @throws CouldNotLoadRules If the PSL rules can not be loaded
     */
    public function getRules(string $url = self::PSL_URL): Rules
    {
        $cacheKey = $this->getCacheKey('PSL', $url);
        $cacheRules = $this->cache->get($cacheKey);

        if (null === $cacheRules && !$this->refreshRules($url)) {
            throw new CouldNotLoadRules(sprintf('Unable to load the public suffix list rules for %s', $url));
        }

        $rules = json_decode($cacheRules ?? $this->cache->get($cacheKey), true);
        if (JSON_ERROR_NONE === json_last_error()) {
            return new Rules($rules);
        }

        throw new CouldNotLoadRules('The public suffix list cache is corrupted: '.json_last_error_msg(), json_last_error());
    }

    /**
     * Returns the cache key according to the source URL.
     */
    private function getCacheKey(string $prefix, string $str): string
    {
        return $prefix.'_FULL_'.md5(strtolower($str));
    }

    /**
     * Downloads, converts and cache the Public Suffix.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful
     */
    public function refreshRules(string $url = self::PSL_URL): bool
    {
        $body = $this->http->getContent($url);
        $cacheData = $this->converter->convert($body);
        $cacheKey = $this->getCacheKey('PSL', $url);

        return $this->cache->set($cacheKey, json_encode($cacheData), $this->ttl);
    }

    /**
     * Gets the Public Suffix List Rules.
     *
     * @throws Exception If the Top Level Domains can not be returned
     */
    public function getTLDs(string $url = self::RZD_URL): TopLevelDomains
    {
        $cacheKey = $this->getCacheKey('RZD', $url);
        $cacheList = $this->cache->get($cacheKey);

        if (null === $cacheList && !$this->refreshTLDs($url)) {
            throw new CouldNotLoadTLDs(sprintf('Unable to load the root zone database from %s', $url));
        }

        $data = json_decode($cacheList ?? $this->cache->get($cacheKey), true);
        if (JSON_ERROR_NONE === json_last_error()) {
            return TopLevelDomains::createFromArray($data);
        }

        throw new CouldNotLoadTLDs('The root zone database cache is corrupted: '.json_last_error_msg(), json_last_error());
    }

    /**
     * Downloads, converts and cache the IANA Root Zone TLD.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful
     *
     * @throws Exception if the source is not validated
     */
    public function refreshTLDs(string $url = self::RZD_URL): bool
    {
        $body = $this->http->getContent($url);
        $cacheData = $this->converter->convertRootZoneDatabase($body);
        $cacheKey = $this->getCacheKey('RZD', $url);

        return $this->cache->set($cacheKey, json_encode($cacheData), $this->ttl);
    }
}
