<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

use Psr\SimpleCache\CacheInterface;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns PHP representations
 * of the Public Suffix List ICANN section
 */
final class Manager
{
    const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var HttpClient
     */
    private $http;

    /**
     * new instance.
     *
     * @param CacheInterface $cache
     * @param HttpClient     $http
     */
    public function __construct(CacheInterface $cache, HttpClient $http)
    {
        $this->cache = $cache;
        $this->http = $http;
    }

    /**
     * Gets the Public Suffix List Rules.
     *
     * @param string $source_url the Public Suffix List URL
     *
     * @return Rules
     */
    public function getRules(string $source_url = self::PSL_URL): Rules
    {
        $cacheKey = $this->getCacheKey($source_url);
        $rules = $this->cache->get($cacheKey);
        if (null !== $rules) {
            return new Rules(json_decode($rules, true));
        }

        if (!$this->refreshRules($source_url)) {
            throw new Exception(sprintf('Unable to load the public suffix list rules for %s', $source_url));
        }

        $rules = $this->cache->get($cacheKey);

        return new Rules(json_decode($rules, true));
    }

    /**
     * Returns the cache key according to the source URL.
     *
     * @param string $str
     *
     * @return string
     */
    private function getCacheKey(string $str): string
    {
        static $cacheKeyPrefix = 'PSL-FULL';

        return $cacheKeyPrefix.'-'.md5(strtolower($str));
    }

    /**
     * Downloads, converts and cache the Public Suffix.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful
     *
     * @param string $source_url the Public Suffix List URL
     *
     * @return bool
     */
    public function refreshRules(string $source_url = self::PSL_URL): bool
    {
        $content = $this->http->getContent($source_url);
        $rules = (new Converter())->convert($content);
        if (empty($rules[Rules::ICANN_DOMAINS]) || empty($rules[Rules::PRIVATE_DOMAINS])) {
            return false;
        }

        $cacheKey = $this->getCacheKey($source_url);

        return $this->cache->set($cacheKey, json_encode($rules));
    }
}
