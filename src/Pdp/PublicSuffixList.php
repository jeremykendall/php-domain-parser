<?php
/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

final class PublicSuffixList
{
    use LabelsTrait;

    /**
     * @var array
     */
    private $rules;

    /**
     * PublicSuffixList constructor.
     *
     * @param mixed $rules
     */
    public function __construct($rules = null)
    {
        $this->rules = $this->filterRules($rules);
    }

    /**
     * Filter the rules parameter
     *
     * @param  mixed $rules
     *
     * @return array
     */
    private function filterRules($rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        if (is_string($rules) && file_exists($rules) && is_readable($rules)) {
            return include $rules;
        }

        return $rules ?? include dirname(__DIR__, 2) . '/data/public-suffix-list.php';
    }

    /**
     * Returns PSL public info for a given domain
     *
     * @param string|null $domain
     *
     * @return Domain
     */
    public function query(string $domain = null): Domain
    {
        if (!$this->isMatchable($domain)) {
            return new NullDomain();
        }

        $normalizedDomain = $this->normalize($domain);
        $matchingLabels = $this->findMatchingLabels($this->getLabelsReverse($normalizedDomain), $this->rules);
        if (!empty($matchingLabels)) {
            $publicSuffix = $this->handleMatches($matchingLabels, $domain);

            return new MatchedDomain($domain, $publicSuffix);
        }

        $publicSuffix = $this->handleNoMatches($domain);

        return new UnmatchedDomain($domain, $publicSuffix);
    }

    /**
     * Returns PSL rules
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Tell whether the given domain is valid
     *
     * @param  string|null $domain
     *
     * @return bool
     */
    private function isMatchable($domain): bool
    {
        if ($domain === null) {
            return false;
        }

        if ($this->hasLeadingDot($domain)) {
            return false;
        }

        if ($this->isSingleLabelDomain($domain)) {
            return false;
        }

        if ($this->isIpAddress($domain)) {
            return false;
        }

        return true;
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
        return strtolower(idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46));
    }

    /**
     * Returns the matchinf labels according to the PSL rules
     *
     * @param array $labels
     *
     * @return string[]
     */
    private function findMatchingLabels(array $labels, array $rules): array
    {
        $matches = [];

        foreach ($labels as $label) {
            if ($this->isExceptionRule($label, $rules)) {
                break;
            }

            if ($this->isWildcardRule($rules)) {
                array_unshift($matches, $label);
                break;
            }

            if ($this->matchExists($label, $rules)) {
                array_unshift($matches, $label);
                $rules = $rules[$label];
                continue;
            }

            // Avoids improper parsing when $domain's subdomain + public suffix ===
            // a valid public suffix (e.g. domain 'us.example.com' and public suffix 'us.com')
            //
            // Added by @goodhabit in https://github.com/jeremykendall/php-domain-parser/pull/15
            // Resolves https://github.com/jeremykendall/php-domain-parser/issues/16
            break;
        }

        return $matches;
    }

    /**
     * Returns the suffix if a match is found using PSL rules
     *
     * @param string $domain
     *
     * @return string
     */
    private function handleMatches(array $matches, string $domain): string
    {
        $suffix = implode('.', array_filter($matches, 'strlen'));
        if ($this->isPunycoded($domain)) {
            return $suffix;
        }

        return idn_to_utf8($suffix, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Returns the suffix if no match is found using PSL rules
     *
     * @param string $domain
     *
     * @return string|null
     */
    private function handleNoMatches(string $domain)
    {
        $labels = $this->getLabels($domain);
        $suffix = array_pop($labels);

        if ($this->isPunycoded($domain) || $suffix === null) {
            return $suffix;
        }

        return idn_to_utf8($suffix, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Tell whether the submitted domain is an IP address
     *
     * @param string $domain
     *
     * @return bool
     */
    private function isIpAddress(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Tell whether a PSL exception rule is found
     *
     * @param string $label
     * @param array  $rules
     *
     * @return bool
     */
    private function isExceptionRule(string $label, array $rules): bool
    {
        return $this->matchExists($label, $rules)
            && array_key_exists('!', $rules[$label]);
    }

    /**
     * Tell whether a PSL wildcard rule is found
     *
     * @param array $rules
     *
     * @return bool
     */
    private function isWildcardRule(array $rules): bool
    {
        return array_key_exists('*', $rules);
    }

    /**
     * Tell whether a PSL label matches the given domain label
     *
     * @param string $label
     * @param array  $rules
     *
     * @return bool
     */
    private function matchExists(string $label, array $rules): bool
    {
        return array_key_exists($label, $rules);
    }

    /**
     * Tell whether the domain is punycoded
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
     * Tell whether the domain starts with a dot character
     *
     * @param string $domain
     *
     * @return bool
     */
    private function hasLeadingDot($domain): bool
    {
        return strpos($domain, '.') === 0;
    }
}
