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
     * Returns a new instance from a file path.
     *
     * @param string        $path
     * @param null|resource $context
     *
     * @return self
     */
    public static function createFromPath(string $path, $context = null): self
    {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        if (!($resource = @fopen(...$args))) {
            throw new Exception(sprintf('`%s`: failed to open stream: No such file or directory', $path));
        }

        $content = stream_get_contents($resource);
        fclose($resource);

        return self::createFromString($content);
    }

    /**
     * Returns a new instance from a string.
     *
     * @param string $content
     *
     * @return self
     */
    public static function createFromString(string $content): self
    {
        return new self((new Converter())->convert($content));
    }

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
     * @param string      $section
     *
     * @return Domain
     */
    public function resolve(string $domain = null, string $section = self::ALL_DOMAINS): Domain
    {
        if (!in_array($section, [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, self::ALL_DOMAINS], true)) {
            throw new Exception(sprintf('%s is an unknown Public Suffix List section', $section));
        }

        if (!$this->isMatchable($domain)) {
            return new Domain();
        }

        $publicSuffix = $this->findPublicSuffix($domain, $section);
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
        return null !== $domain
            && strpos($domain, '.') > 0
            && strlen($domain) === strcspn($domain, '][')
            && !filter_var($domain, FILTER_VALIDATE_IP);
    }

    /**
     * Normalizes a domain name.
     *
     * "The domain must be canonicalized in the normal way for hostnames - lower-case, Punycode."
     *
     * @see https://tools.ietf.org/html/rfc3492
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
     * @param string $domain
     * @param string $section
     *
     * @return PublicSuffix
     */
    private function findPublicSuffix(string $domain, string $section): PublicSuffix
    {
        $normalizedDomain = $this->normalize($domain);
        $reverseLabels = array_reverse(explode('.', $normalizedDomain));
        $resultIcann = $this->findPublicSuffixFromSection($reverseLabels, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $resultIcann;
        }

        $resultPrivate = $this->findPublicSuffixFromSection($reverseLabels, self::PRIVATE_DOMAINS);
        if (count($resultPrivate) > count($resultIcann)) {
            return $resultPrivate;
        }

        if (self::ALL_DOMAINS === $section) {
            return $resultIcann;
        }

        return new PublicSuffix();
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     *
     * @param array  $labels
     * @param string $section
     *
     * @return PublicSuffix
     */
    private function findPublicSuffixFromSection(array $labels, string $section): PublicSuffix
    {
        $rules = $this->rules[$section] ?? null;
        $matches = [];
        foreach ($labels as $label) {
            //match exception rule
            if (isset($rules[$label], $rules[$label]['!'])) {
                break;
            }

            //match wildcard rule
            if (isset($rules['*'])) {
                $matches[] = $label;
                break;
            }

            //no match found
            if (!isset($rules[$label])) {
                break;
            }

            $matches[] = $label;
            $rules = $rules[$label];
        }

        $foundLabels = array_reverse(array_filter($matches, 'strlen'));
        if (empty($foundLabels)) {
            return new PublicSuffix();
        }

        return new PublicSuffix(implode('.', $foundLabels), $section);
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
        return false !== strpos($domain, 'xn--');
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
