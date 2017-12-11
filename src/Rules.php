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

/**
 * A class to resolve domain name against the Public Suffix list
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Rules
{
    const ALL_DOMAINS = 'ALL_DOMAINS';
    const ICANN_DOMAINS = 'ICANN_DOMAINS';
    const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    /**
     * PSL rules as a multidimentional associative array
     *
     * @var array
     */
    private $rules;

    /**
     * new instance.
     *
     * @param array $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Returns PSL ICANN public info for a given domain.
     *
     * @param string|null $domain
     * @param string      $type
     *
     * @return Domain
     */
    public function resolve(string $domain = null, string $type = self::ALL_DOMAINS): Domain
    {
        if (!in_array($type, [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, self::ALL_DOMAINS], true)) {
            throw new Exception(sprintf('%s is an unknown Domain type', $type));
        }

        if (!$this->isMatchable($domain)) {
            return new Domain(null, new PublicSuffix());
        }

        $publicSuffix = $this->findPublicSuffix($type, $domain);
        if (null === $publicSuffix->getContent()) {
            return new Domain($domain, $this->handleNoMatches($domain));
        }

        return new Domain($domain, $this->handleMatches($domain, $publicSuffix));
    }

    /**
     * Tells whether the given domain can be resolved.
     *
     * @param string|null $domain
     *
     * @return bool
     */
    private function isMatchable($domain): bool
    {
        return $domain !== null
            && strpos($domain, '.') > 0
            && strlen($domain) === strcspn($domain, '][')
            && !filter_var($domain, FILTER_VALIDATE_IP);
    }

    /**
     * Normalizes a domain name.
     *
     * "The domain must be canonicalized in the normal way for hostnames - lower-case, Punycode."
     *
     * @see http://www.ietf.org/rfc/rfc3492.txt
     *
     * @param string $domain
     *
     * @return string
     */
    private function normalize(string $domain): string
    {
        if (false !== strpos($domain, '%')) {
            $domain = rawurldecode($domain);
        }

        $normalize = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        if (false === $normalize) {
            return '';
        }

        return strtolower($normalize);
    }

    /**
     * Returns the matched public suffix.
     *
     * @param string $type
     * @param string $domain
     *
     * @return PublicSuffix
     */
    private function findPublicSuffix(string $type, string $domain): PublicSuffix
    {
        $normalizedDomain = $this->normalize($domain);
        $reverseLabels = array_reverse(explode('.', $normalizedDomain));

        $resultIcann = $this->findPublicSuffixFromSection(self::ICANN_DOMAINS, $reverseLabels);
        if (self::ICANN_DOMAINS === $type) {
            return $resultIcann;
        }

        $resultPrivate = $this->findPublicSuffixFromSection(self::PRIVATE_DOMAINS, $reverseLabels);
        if (count($resultPrivate) > count($resultIcann)) {
            return $resultPrivate;
        }

        if (self::ALL_DOMAINS === $type) {
            return $resultIcann;
        }

        return new PublicSuffix();
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     *
     * @param string $type
     * @param array  $labels
     *
     * @return PublicSuffix
     */
    private function findPublicSuffixFromSection(string $type, array $labels): PublicSuffix
    {
        $rules = $this->rules[$type];
        $matches = [];
        foreach ($labels as $label) {
            //match exception rule
            if (isset($rules[$label], $rules[$label]['!'])) {
                break;
            }

            //match wildcard rule
            if (isset($rules['*'])) {
                array_unshift($matches, $label);
                break;
            }

            //no match found
            if (!isset($rules[$label])) {
                // Avoids improper parsing when $domain's subdomain + public suffix ===
                // a valid public suffix (e.g. domain 'us.example.com' and public suffix 'us.com')
                //
                // Added by @goodhabit in https://github.com/jeremykendall/php-domain-parser/pull/15
                // Resolves https://github.com/jeremykendall/php-domain-parser/issues/16
                break;
            }

            array_unshift($matches, $label);
            $rules = $rules[$label];
        }

        $found = array_filter($matches, 'strlen');
        if (empty($found)) {
            return new PublicSuffix();
        }

        return new PublicSuffix(implode('.', $found), $type);
    }

    /**
     * Returns a PublicSuffix if none was found using the PSL.
     *
     * @param string $domain
     *
     * @return PublicSuffix
     */
    private function handleNoMatches(string $domain): PublicSuffix
    {
        $labels = explode('.', $domain);
        $publicSuffix = array_pop($labels);

        if ($this->isPunycoded($domain)) {
            return new PublicSuffix($publicSuffix);
        }

        $publicSuffix = idn_to_utf8($publicSuffix, 0, INTL_IDNA_VARIANT_UTS46);
        if (false !== $publicSuffix) {
            return new PublicSuffix($publicSuffix);
        }

        return new PublicSuffix();
    }

    /**
     * Tells whether the domain is punycoded.
     *
     * @param string $domain
     *
     * @return bool
     */
    private function isPunycoded(string $domain): bool
    {
        return strpos($domain, 'xn--') !== false;
    }

    /**
     * Returns a PublicSuffix if one was found using the PSL.
     *
     * @param string       $domain
     * @param PublicSuffix $publicSuffix
     *
     * @return PublicSuffix
     */
    private function handleMatches($domain, PublicSuffix $publicSuffix): PublicSuffix
    {
        if ($this->isPunycoded($domain)) {
            return $publicSuffix;
        }

        return new PublicSuffix(
            idn_to_utf8($publicSuffix->getContent(), 0, INTL_IDNA_VARIANT_UTS46),
            $publicSuffix->isICANN() ? self::ICANN_DOMAINS : self::PRIVATE_DOMAINS
        );
    }
}
