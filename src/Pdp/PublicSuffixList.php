<?php

declare(strict_types=1);

/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
namespace Pdp;

final class PublicSuffixList implements \Countable
{
    use LabelsTrait;

    /**
     * @var array
     */
    private $rules;

    /**
     * PublicSuffixList constructor.
     * @param mixed $rules
     */
    public function __construct($rules = null)
    {
        if (is_string($rules) && file_exists($rules) && is_readable($rules)) {
            $this->rules = include $rules;
        }

        if ($rules === null) {
            $this->rules = include dirname(__DIR__, 2) . '/data/public-suffix-list.php';
        }

        if (is_array($rules)) {
            $this->rules = $rules;
        }
        $this->rules = $rules ?? include dirname(__DIR__, 2) . '/data/public-suffix-list.php';
    }

    public function query(string $domain = null): Domain
    {
        if (!$this->isMatchable($domain)) {
            return new NullDomain();
        }

        $input = $domain;
        $domain = $this->normalize($domain);
        $matchingLabels = $this->findMatchingLabels($this->getLabelsReverse($domain), $this->rules);
        $publicSuffix = empty($matchingLabels) ? $this->handleNoMatches($domain) : $this->processMatches($matchingLabels);

        if ($this->isPunycoded($input) === false) {
            $publicSuffix = idn_to_utf8($publicSuffix, 0, INTL_IDNA_VARIANT_UTS46);
        }

        if (count($matchingLabels) > 0) {
            return new MatchedDomain($input, $publicSuffix, true);
        }

        return new UnmatchedDomain($input, $publicSuffix, false);
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @TODO: Remove. Bandaid to fix failing test.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->rules);
    }

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

    private function processMatches(array $matches): string
    {
        return implode('.', array_filter($matches, 'strlen'));
    }

    private function isIpAddress(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_IP) !== false;
    }

    private function isExceptionRule(string $label, array $rules): bool
    {
        return $this->matchExists($label, $rules)
            && array_key_exists('!', $rules[$label]);
    }

    private function isWildcardRule(array $rules): bool
    {
        return array_key_exists('*', $rules);
    }

    private function matchExists(string $label, array $rules): bool
    {
        return array_key_exists($label, $rules);
    }

    private function handleNoMatches(string $domain): string
    {
        $labels = $this->getLabels($domain);

        return array_pop($labels);
    }

    private function isPunycoded(string $input): bool
    {
        return strpos($input, 'xn--') !== false;
    }

    private function hasLeadingDot($domain): bool
    {
        return strpos($domain, '.') === 0;
    }
}
