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
use function filter_var;
use function gettype;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function md5;
use function sprintf;
use function strtolower;
use const DATE_ATOM;
use const FILTER_VALIDATE_INT;
use const IDNA_DEFAULT;
use const JSON_ERROR_NONE;

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
    public const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    public const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

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
     * new instance.
     *
     * @param CacheInterface $cache
     * @param HttpClient     $http
     * @param null|mixed     $ttl
     */
    public function __construct(CacheInterface $cache, HttpClient $http, $ttl = null)
    {
        $this->cache = $cache;
        $this->http = $http;
        $this->ttl = $this->filterTtl($ttl);
    }

    /**
     * Gets the Public Suffix List Rules.
     *
     * @param string     $url               the Public Suffix List URL
     * @param null|mixed $ttl               the cache TTL
     * @param int        $asciiIDNAOption
     * @param int        $unicodeIDNAOption
     *
     * @throws CouldNotLoadRules
     *
     * @return Rules
     */
    public function getRules(
        string $url = self::PSL_URL,
        $ttl = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): Rules {
        $key = $this->getCacheKey('PSL', $url);
        $data = $this->cache->get($key);

        if (null === $data && !$this->refreshRules($url, $ttl)) {
            throw new CouldNotLoadRules(sprintf('Unable to load the public suffix list rules for %s', $url));
        }

        $data = json_decode($data ?? $this->cache->get($key), true);
        if (JSON_ERROR_NONE === json_last_error()) {
            return new Rules($data, $asciiIDNAOption, $unicodeIDNAOption);
        }

        throw new CouldNotLoadRules(sprintf('The public suffix list cache is corrupted: %s', json_last_error_msg()), json_last_error());
    }

    /**
     * Downloads, converts and cache the Public Suffix.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful
     *
     * @param string     $url the Public Suffix List URL
     * @param null|mixed $ttl the cache TTL
     *
     * @throws HttpClientException
     *
     * @return bool
     */
    public function refreshRules(string $url = self::PSL_URL, $ttl = null): bool
    {
        static $converter;

        $converter = $converter ?? new Converter();
        $data = json_encode($converter->convert($this->http->getContent($url)));

        return $this->cache->set($this->getCacheKey('PSL', $url), $data, $this->filterTtl($ttl) ?? $this->ttl);
    }

    /**
     * Gets the Public Suffix List Rules.
     *
     * @param string     $url               the IANA Root Zone Database URL
     * @param null|mixed $ttl               the cache TTL
     * @param int        $asciiIDNAOption
     * @param int        $unicodeIDNAOption
     *
     * @throws CouldNotLoadTLDs
     *
     * @return TopLevelDomains
     */
    public function getTLDs(
        string $url = self::RZD_URL,
        $ttl = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): TopLevelDomains {
        $key = $this->getCacheKey('RZD', $url);
        $data = $this->cache->get($key);

        if (null === $data && !$this->refreshTLDs($url, $ttl)) {
            throw new CouldNotLoadTLDs(sprintf('Unable to load the root zone database from %s', $url));
        }

        $data = json_decode($data ?? $this->cache->get($key), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new CouldNotLoadTLDs(
                sprintf('The root zone database cache is corrupted: %s', json_last_error_msg()),
                json_last_error()
            );
        }

        if (!isset($data['records'], $data['version'], $data['modifiedDate'])) {
            throw new CouldNotLoadTLDs('The root zone database cache content is corrupted');
        }

        /** @var DateTimeImmutable $modifiedDate */
        $modifiedDate = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['modifiedDate']);

        return new TopLevelDomains(
            $data['records'],
            $data['version'],
            $modifiedDate,
            $asciiIDNAOption,
            $unicodeIDNAOption
        );
    }

    /**
     * Downloads, converts and cache the IANA Root Zone TLD.
     * If a local cache already exists, it will be overwritten.
     * Returns true if the refresh was successful.
     *
     * @param string     $url the IANA Root Zone Database URL
     * @param null|mixed $ttl the cache TTL
     *
     * @throws HttpClientException
     *
     * @return bool
     */
    public function refreshTLDs(string $url = self::RZD_URL, $ttl = null): bool
    {
        static $converter;

        $converter = $converter ?? new TLDConverter();
        $data = json_encode($converter->convert($this->http->getContent($url)));

        return $this->cache->set($this->getCacheKey('RZD', $url), $data, $this->filterTtl($ttl) ?? $this->ttl);
    }

    /**
     * set the cache TTL.
     *
     * @param null|mixed $ttl the cache TTL
     *
     * @throws TypeError if the value type is not recognized
     *
     * @return DateInterval|null
     */
    private function filterTtl($ttl)
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
     * Returns the cache key according to the source URL.
     *
     * @param string $prefix
     * @param string $str
     *
     * @return string
     */
    private function getCacheKey(string $prefix, string $str): string
    {
        return sprintf('%s_FULL_%s', $prefix, md5(strtolower($str)));
    }
}
