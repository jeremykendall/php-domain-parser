<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2014 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp;

use Pdp\Uri\Url;
use Pdp\Uri\Url\Host;

/**
 * Parser
 *
 * This class is reponsible for Public Suffix List based url parsing
 */
class Parser
{
    const SCHEME_PATTERN = '#^(http|ftp)s?://#i';

    /**
     * @var PublicSuffixList Public Suffix List
     */
    protected $publicSuffixList;

    /**
     * Public constructor
     *
     * @codeCoverageIgnore
     * @param PublicSuffixList $publicSuffixList Instance of PublicSuffixList
     */
    public function __construct(PublicSuffixList $publicSuffixList)
    {
        $this->publicSuffixList = $publicSuffixList;
    }

    /**
     * Parses url
     *
     * @param  string $url Url to parse
     * @return Url    Object representation of url
     */
    public function parseUrl($url)
    {
        $elem = array(
            'scheme'   => null,
            'user'     => null,
            'pass'     => null,
            'host'     => null,
            'port'     => null,
            'path'     => null,
            'query'    => null,
            'fragment' => null,
        );

        if (preg_match(self::SCHEME_PATTERN, $url) === 0) {
            $url = 'http://' . preg_replace('#^//#', '', $url, 1);
        }

        $parts = mb_parse_url($url);

        if ($parts === false) {
            throw new \InvalidArgumentException(sprintf('Invalid url %s', $url));
        }

        $elem = (array) $parts + $elem;

        $host = $this->parseHost($parts['host']);

        return new Url(
            $elem['scheme'],
            $elem['user'],
            $elem['pass'],
            $host,
            $elem['port'],
            $elem['path'],
            $elem['query'],
            $elem['fragment']
        );
    }

    /**
     * Parses host part of url
     *
     * @param  string $host Host part of url
     * @return Host   Object representation of host portion of url
     */
    public function parseHost($host)
    {
        $host = mb_strtolower($host, 'UTF-8');

        $subdomain = null;
        $registerableDomain = null;
        $publicSuffix = null;

        // Fixes #22: Single label domains are set as Host::$host and all other
        // properties are null.
        if (strpos($host, '.') !== false) {
            $subdomain = $this->getSubdomain($host);
            $registerableDomain = $this->getRegisterableDomain($host);
            $publicSuffix = $this->getPublicSuffix($host);
        }

        return new Host(
            $subdomain,
            $registerableDomain,
            $publicSuffix,
            $host
        );
    }

    /**
     * Returns the public suffix portion of provided host
     *
     * @param  string $host host
     * @return string public suffix
     */
    public function getPublicSuffix($host)
    {
        if (strpos($host, '.') === 0) {
            return null;
        }

        // Fixes #22: If a single label domain makes it this far (e.g.,
        // localhost, foo, etc.), this stops it from incorrectly being set as
        // the  public suffix.
        if (strpos($host, '.') === false) {
            return null;
        }

        $parts = array_reverse(explode('.', $host));
        $publicSuffix = array();
        $publicSuffixList = $this->publicSuffixList;

        foreach ($parts as $part) {
            if (array_key_exists($part, $publicSuffixList)
                && array_key_exists('!', $publicSuffixList[$part])) {
                break;
            }

            if (array_key_exists($part, $publicSuffixList)) {
                array_unshift($publicSuffix, $part);
                $publicSuffixList = $publicSuffixList[$part];
                continue;
            }

            if (array_key_exists('*', $publicSuffixList)) {
                array_unshift($publicSuffix, $part);
                $publicSuffixList = $publicSuffixList['*'];
                continue;
            }

            // Avoids improper parsing when $host's subdomain + public suffix ===
            // a valid public suffix (e.g. host 'us.example.com' and public suffix 'us.com')
            //
            // Added by @goodhabit in https://github.com/jeremykendall/php-domain-parser/pull/15
            // Resolves https://github.com/jeremykendall/php-domain-parser/issues/16
            break;
        }

        // Apply algorithm rule #2: If no rules match, the prevailing rule is "*".
        if (empty($publicSuffix)) {
            $publicSuffix[0] = $parts[0];
        }

        return implode('.', array_filter($publicSuffix, 'strlen'));
    }

    /**
     * Returns registerable domain portion of provided host
     *
     * Per the test cases provided by Mozilla
     * (http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1),
     * this method should return null if the domain provided is a public suffix.
     *
     * @param  string $host host
     * @return string registerable domain
     */
    public function getRegisterableDomain($host)
    {
        if (strpos($host, '.') === false) {
            return null;
        }

        $punycoded = (strpos($host, 'xn--') !== false);

        if ($punycoded) {
            $host = idn_to_utf8($host);
        }

        $host = mb_strtolower($host, 'UTF-8');
        $publicSuffix = $this->getPublicSuffix($host);

        if ($publicSuffix === null || $host == $publicSuffix) {
            return null;
        }

        $publicSuffixParts = array_reverse(explode('.', $publicSuffix));
        $hostParts = array_reverse(explode('.', $host));
        $registerableDomainParts = array_slice($hostParts, 0, count($publicSuffixParts) + 1);

        $registerableDomain = implode('.', array_reverse($registerableDomainParts));

        if ($punycoded) {
            $registerableDomain = idn_to_ascii($registerableDomain);
        }

        return $registerableDomain;
    }

    /**
     * Returns the subdomain portion of provided host
     *
     * @param  string $host host
     * @return string subdomain
     */
    public function getSubdomain($host)
    {
        $registerableDomain = $this->getRegisterableDomain($host);

        if ($registerableDomain === null || $host == $registerableDomain) {
            return null;
        }

        $registerableDomainParts = array_reverse(explode('.', $registerableDomain));
        $hostParts = array_reverse(explode('.', $host));
        $subdomainParts = array_slice($hostParts, count($registerableDomainParts));

        return implode('.', array_reverse($subdomainParts));
    }

    /**
     * DEPRECATED: UTF-8 aware parse_url() replacement.
     *
     * Taken from php.net manual comments {@link http://php.net/manual/en/function.parse-url.php#114817}
     *
     * @deprecated Please use mb_parse_url instead. Will be removed in version 2.0.
     * @codeCoverageIgnore
     *
     * @param  string  $url       The URL to parse
     * @param  integer $component Specify one of PHP_URL_SCHEME, PHP_URL_HOST,
     *                            PHP_URL_PORT, PHP_URL_USER, PHP_URL_PASS, PHP_URL_PATH, PHP_URL_QUERY or
     *                            PHP_URL_FRAGMENT to retrieve just a specific URL component as a string
     *                            (except when PHP_URL_PORT is given, in which case the return value will
     *                            be an integer).
     * @return mixed   See parse_url documentation {@link http://us1.php.net/parse_url}
     */
    public function mbParseUrl($url, $component = -1)
    {
        $enc_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) {
                return urlencode($matches[0]);
            },
            $url
        );

        $parts = parse_url($enc_url, $component);

        if ($parts === false) {
            return $parts;
        }

        if (is_array($parts)) {
            foreach ($parts as $name => $value) {
                $parts[$name] = urldecode($value);
            }
        } else {
            $parts = urldecode($parts);
        }

        return $parts;
    }
}
