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

final class Rules
{
    /**
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
    public function resolve(string $domain = null, string $type = PublicSuffix::ALL): Domain
    {
        if (!in_array($type, [PublicSuffix::PRIVATE, PublicSuffix::ICANN, PublicSuffix::ALL], true)) {
            throw new Exception(sprintf('%s is an unknown Public Suffix Section', $type));
        }

        if (!$this->isMatchable($domain)) {
            return new Domain(null, new PublicSuffix());
        }

        $normalizedDomain = $this->normalize($domain);
        $reverseLabels = array_reverse(explode('.', $normalizedDomain));
        $publicSuffix = $this->findPublicSuffix($type, $reverseLabels);
        if (null === $publicSuffix->getContent()) {
            return $this->handleNoMatches($domain);
        }

        return $this->handleMatches($domain, $publicSuffix);
    }

    /**
     * Tells whether the given domain is valid.
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
     * Normalize domain.
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
     * Returns the matched public suffix and its type
     *
     * @param string $type
     * @param array  $labels
     *
     * @return PublicSuffix
     */
    private function findPublicSuffix(string $type, array $labels): PublicSuffix
    {
        $resultIcann = $this->findPublicSuffixFromSection(PublicSuffix::ICANN, $labels);
        if (PublicSuffix::ICANN === $type) {
            return $resultIcann;
        }

        $resultPrivate = $this->findPublicSuffixFromSection(PublicSuffix::PRIVATE, $labels);
        if (PublicSuffix::ALL === $type) {
            return count($resultIcann) >= count($resultPrivate) ? $resultIcann : $resultPrivate;
        }

        if (count($resultPrivate) > count($resultIcann)) {
            return $resultPrivate;
        }

        return new PublicSuffix();
    }

    /**
     * Returns the matched public suffix using a given section type
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
     * Returns the Domain value object.
     *
     * @param string $domain
     * @param string $publicSuffix
     * @param string $type
     *
     * @return Domain
     */
    private function handleMatches(string $domain, PublicSuffix $publicSuffix): Domain
    {
        if (!$this->isPunycoded($domain)) {
            $publicSuffix = new PublicSuffix(
                idn_to_utf8($publicSuffix->getContent(), 0, INTL_IDNA_VARIANT_UTS46),
                $publicSuffix->isICANN() ? PublicSuffix::ICANN : PublicSuffix::PRIVATE
            );
        }

        return new Domain($domain, $publicSuffix);
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
     * Returns the Domain value object.
     *
     * @param string $domain
     *
     * @return Domain
     */
    private function handleNoMatches(string $domain): Domain
    {
        $labels = explode('.', $domain);
        $publicSuffix = array_pop($labels);

        if (!$this->isPunycoded($domain)) {
            $publicSuffix = idn_to_utf8($publicSuffix, 0, INTL_IDNA_VARIANT_UTS46);
            if (false === $publicSuffix) {
                $publicSuffix = null;
            }
        }

        return new Domain($domain, new PublicSuffix($publicSuffix));
    }
}
