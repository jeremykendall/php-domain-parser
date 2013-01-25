<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2013 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp;

/**
 * Domain Parser
 *
 * This class is reponsible for domain parsing
 */
class DomainParser
{

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
     * @return Domain Parsed domain object
     */
    public function parse($url)
    {
        preg_match('#^https?://#i', $url, $schemeMatches);

        if (empty($schemeMatches)) {
            $url = 'http://' . $url;
        }

        $parts = parse_url($url);

        $parts['registerableDomain'] = $this->getRegisterableDomain($parts['host']);
        $parts['publicSuffix'] = substr($parts['registerableDomain'], strpos($parts['registerableDomain'], '.') + 1);

        $registerableDomainParts = explode('.', $parts['registerableDomain']);
        $hostParts = explode('.', $parts['host']);
        $subdomainParts = array_diff($hostParts, $registerableDomainParts);
        $parts['subdomain'] = implode('.', $subdomainParts);

        if (empty($parts['subdomain'])) {
            $parts['subdomain'] = null;
        }

        return new Domain($parts);
    }

    /**
     * Returns registerable domain portion of provided domain
     *
     * Per the test cases provided by Mozilla
     * (http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1),
     * this method should return null if the domain provided is a public suffix.
     *
     * This method is based heavily on the code found in regDomain.inc.php
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/PHP/regDomain.inc.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param  string $domain Domain
     * @return string Registerable domain
     */
    public function getRegisterableDomain($domain)
    {
        if (strpos($domain, '.') === 0) {
            return null;
        }

        $publicSuffix = array();

        $domainParts = explode('.', strtolower($domain));
        $registerableDomain = $this->breakdown($domainParts, $this->publicSuffixList, $publicSuffix);

        // Remove null values
        $publicSuffix = array_filter($publicSuffix, 'strlen');

        if ($registerableDomain == implode('.', $publicSuffix)) {
            return null;
        }

        return $registerableDomain;
    }

    /**
     * Compares domain parts to the Public Suffix List
     *
     * This method is based heavily on the code found in regDomain.inc.php.
     *
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/PHP/regDomain.inc.php regDomain.inc.php
     *
     * @param array $domainParts      Domain parts as array
     * @param array $publicSuffixList Array representation of the Public Suffix
     * List
     * @param  array  $publicSuffix Builds the public suffix during recursion
     * @return string Public suffix
     */
    public function breakdown(array $domainParts, $publicSuffixList, &$publicSuffix)
    {
        $part = array_pop($domainParts);
        $result = null;

        if (array_key_exists($part, $publicSuffixList) && array_key_exists('!', $publicSuffixList[$part])) {
            return $part;
        }

        if (array_key_exists($part, $publicSuffixList)) {
            array_unshift($publicSuffix, $part);
            $result = $this->breakdown($domainParts, $publicSuffixList[$part], $publicSuffix);
        }

        if (array_key_exists('*', $publicSuffixList)) {
            array_unshift($publicSuffix, $part);
            $result = $this->breakdown($domainParts, $publicSuffixList['*'], $publicSuffix);
        }

        if ($result === null) {
            return $part;
        }

        return $result . '.' . $part;
    }

}
