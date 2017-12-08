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
    public function resolve(string $domain = null, string $type = Domain::UNKNOWN_DOMAIN): Domain
    {
        if (!$this->isMatchable($domain)) {
            return new Domain();
        }

        $normalizedDomain = $this->normalize($domain);
        $reverseLabels = array_reverse(explode('.', $normalizedDomain));
        list($publicSuffix, $type) = $this->findPublicSuffix($type, $reverseLabels);
        if (null === $publicSuffix) {
            return $this->handleNoMatches($domain);
        }

        return $this->handleMatches($domain, $publicSuffix, $type);
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
     * @return array
     */
    private function findPublicSuffix(string $type, array $labels): array
    {
        if (in_array($type, [Domain::PRIVATE_DOMAIN, Domain::ICANN_DOMAIN], true)) {
            return $this->findPublicSuffixFromSection($type, $labels);
        }

        $resultPrivate = $this->findPublicSuffixFromSection(Domain::PRIVATE_DOMAIN, $labels);
        $resultIcann = $this->findPublicSuffixFromSection(Domain::ICANN_DOMAIN, $labels);

        $privateSuffix = $resultPrivate[0];
        $icannSuffix = $resultIcann[0];

        if (isset($icannSuffix, $privateSuffix)) {
            return strlen($privateSuffix) > strlen($icannSuffix) ? $resultPrivate : $resultIcann;
        }

        if (null === $privateSuffix && null === $icannSuffix) {
            return [null, Domain::UNKNOWN_DOMAIN];
        }

        return null === $privateSuffix ? $resultIcann : $resultPrivate;
    }

    /**
     * Returns the matched public suffix using a given section type
     *
     * @param string $type
     * @param array  $labels
     *
     * @return array
     */
    private function findPublicSuffixFromSection(string $type, array $labels): array
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
        if (count($found) == 1 && $type === Domain::PRIVATE_DOMAIN) {
            return [null, $type = Domain::UNKNOWN_DOMAIN];
        }

        $publicSuffix = empty($matches) ? null : implode('.', $found);

        return [$publicSuffix, $type];
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
    private function handleMatches(string $domain, string $publicSuffix, string $type): Domain
    {
        if (!$this->isPunycoded($domain)) {
            $publicSuffix = idn_to_utf8($publicSuffix, 0, INTL_IDNA_VARIANT_UTS46);
        }

        return new Domain($domain, $publicSuffix, $type);
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

        return new Domain($domain, $publicSuffix);
    }
}
