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
        $parts['publicSuffix'] = $this->getPublicSuffix($parts['host']);
        $parts['registerableDomain'] = $this->getRegisterableDomain($parts['host']);
        $parts['subdomain'] = $this->getSubdomain($parts['host']);

        return new Domain($parts);
    }

    /**
     * Returns the public suffix portion of provided domain
     *
     * @param  string $domain domain
     * @return string public suffix
     */
    public function getPublicSuffix($domain)
    {
        if (strpos($domain, '.') === 0) {
            return null;
        }

        $domain = strtolower($domain);
        $parts = array_reverse(explode('.', $domain));
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
        }

        // Apply algorithm rule #2: If no rules match, the prevailing rule is "*".
        if (empty($publicSuffix)) {
            $publicSuffix[0] = $parts[0];
        }

        return implode('.', array_filter($publicSuffix, 'strlen'));
    }

    /**
     * Returns registerable domain portion of provided domain
     *
     * Per the test cases provided by Mozilla
     * (http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1),
     * this method should return null if the domain provided is a public suffix.
     *
     * @param  string $domain domain
     * @return string registerable domain
     */
    public function getRegisterableDomain($domain)
    {
        if (strpos($domain, '.') === false) {
            return null;
        }

        $domain = strtolower($domain);
        $publicSuffix = $this->getPublicSuffix($domain);

        if ($publicSuffix === null || $domain == $publicSuffix) {
            return null;
        }

        $publicSuffixParts = array_reverse(explode('.', $publicSuffix));
        $domainParts = array_reverse(explode('.', $domain));
        $registerableDomainParts = array_slice($domainParts, 0, count($publicSuffixParts) + 1);

        return implode('.', array_reverse($registerableDomainParts));
    }

    /**
     * Returns the subdomain portion of provided domain
     *
     * @param  string $domain domain
     * @return string subdomain
     */
    public function getSubdomain($domain)
    {
        $domain = strtolower($domain);
        $registerableDomain = $this->getRegisterableDomain($domain);

        if ($registerableDomain === null || $domain == $registerableDomain) {
            return null;
        }

        $registerableDomainParts = array_reverse(explode('.', $registerableDomain));
        $domainParts = array_reverse(explode('.', $domain));
        $subdomainParts = array_slice($domainParts, count($registerableDomainParts));

        return implode('.', array_reverse($subdomainParts));
    }

}
