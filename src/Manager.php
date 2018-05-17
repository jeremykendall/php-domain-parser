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

use Pdp\Exception\CouldNotLoadRules;
use Psr\SimpleCache\CacheInterface;

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
     * @throws CouldNotLoadRules If the PSL rules can not be loaded
     *
     * @return Rules
     */
    public function getRules(string $source_url = self::PSL_URL): Rules
    {
        $cacheKey = $this->getCacheKey($source_url);
        $cacheRules = $this->cache->get($cacheKey);

        if (null === $cacheRules && !$this->refreshRules($source_url)) {
            throw new CouldNotLoadRules(sprintf('Unable to load the public suffix list rules for %s', $source_url));
        }

        $rules = json_decode($cacheRules ?? $this->cache->get($cacheKey), true);
        if (JSON_ERROR_NONE === json_last_error()) {
            return new Rules($rules);
        }

        throw new CouldNotLoadRules('The public suffix list cache is corrupted: '.json_last_error_msg(), json_last_error());
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
        return 'PSL_FULL_'.md5(strtolower($str));
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
        static $converter;

        $converter = $converter ?? new Converter();

        return $this->cache->set(
            $this->getCacheKey($source_url),
            json_encode($converter->convert($this->http->getContent($source_url)))
        );
    }
}
