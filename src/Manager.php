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
use SplTempFileObject;

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
     * Gets ICANN Public Suffix List Rules.
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
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten.
     *
     * Returns true if all list are correctly refreshed
     *
     * @param string $source_url the Public Suffix List URL
     *
     * @return bool
     */
    public function refreshRules(string $source_url = self::PSL_URL): bool
    {
        $content = $this->http->getContent($source_url);
        $rules = $this->parse($content);
        if (empty($rules[Domain::ICANN_DOMAIN]) || empty($rules[Domain::PRIVATE_DOMAIN])) {
            return false;
        }

        return $this->cache->set($this->getCacheKey($source_url), json_encode($rules));
    }

    /**
     * Parses text representation of list to associative, multidimensional array.
     *
     * @param string $content the Public SUffix List as a SplFileObject
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    private function parse(string $content): array
    {
        $section = Domain::UNKNOWN_DOMAIN;
        $rules = [Domain::ICANN_DOMAIN => [], Domain::PRIVATE_DOMAIN => []];
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        foreach ($file as $line) {
            $section = $this->getPslSection($section, $line);
            if ($section !== Domain::UNKNOWN_DOMAIN && strpos($line, '//') === false) {
                $rules[$section] = $this->addRule($rules[$section], explode('.', $line));
            }
        }

        return $rules;
    }

    /**
     * Tell whether the line can be converted for a given domain.
     *
     * @param bool   $section the previous status
     * @param string $line    the current file line
     *
     * @return string
     */
    private function getPslSection(string $section, string $line): string
    {
        if ($section == Domain::UNKNOWN_DOMAIN && strpos($line, '// ===BEGIN ICANN DOMAINS===') === 0) {
            return Domain::ICANN_DOMAIN;
        }

        if ($section == Domain::ICANN_DOMAIN && strpos($line, '// ===END ICANN DOMAINS===') === 0) {
            return Domain::UNKNOWN_DOMAIN;
        }

        if ($section == Domain::UNKNOWN_DOMAIN && strpos($line, '// ===BEGIN PRIVATE DOMAINS===') === 0) {
            return Domain::PRIVATE_DOMAIN;
        }

        if ($section == Domain::PRIVATE_DOMAIN && strpos($line, '// ===END PRIVATE DOMAINS===') === 0) {
            return Domain::UNKNOWN_DOMAIN;
        }

        return $section;
    }

    /**
     * Recursive method to build the array representation of the Public Suffix List.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * @see https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param array $list       Initially an empty array, this eventually
     *                          becomes the array representation of the Public Suffix List
     * @param array $rule_parts One line (rule) from the Public Suffix List
     *                          exploded on '.', or the remaining portion of that array during recursion
     *
     * @return array
     */
    private function addRule(array $list, array $rule_parts): array
    {
        $part = array_pop($rule_parts);

        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."

        $part = idn_to_ascii($part, 0, INTL_IDNA_VARIANT_UTS46);
        $isDomain = true;
        if (strpos($part, '!') === 0) {
            $part = substr($part, 1);
            $isDomain = false;
        }

        if (!isset($list[$part])) {
            $list[$part] = $isDomain ? [] : ['!' => ''];
        }

        if ($isDomain && !empty($rule_parts)) {
            $list[$part] = $this->addRule($list[$part], $rule_parts);
        }

        return $list;
    }
}
