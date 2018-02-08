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
    use IDNAConverterTrait;

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
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['rules']);
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
     * Tell whether the submitted section if supported by the current object
     *
     * @param string $section
     *
     * @return bool
     */
    public function supports(string $section): bool
    {
        return self::ALL_DOMAINS === $section
            || (isset($this->rules[$section]) && is_array($this->rules[$section]) && !empty($this->rules[$section]));
    }

    /**
     * Determines the public suffix for a given domain.
     *
     * @param string|null $domain
     * @param string      $section
     *
     * @throws Exception
     *                   If the Domain is invalid or malformed
     *                   If the section is invalid or not supported
     *                   If the PublicSuffix can not be converted using against the domain encoding.
     *
     * @return PublicSuffix
     */
    public function getPublicSuffix(string $domain = null, string $section = self::ALL_DOMAINS): PublicSuffix
    {
        if (!$this->isMatchable($domain)) {
            throw new Exception(sprintf('The submitted domain `%s` is invalid or malformed', $domain));
        }
        $this->validateSection($section);

        return $this->findPublicSuffix($domain, $section);
    }

    /**
     * Returns PSL info for a given domain.
     *
     * @param string|null $domain
     * @param string      $section
     *
     * @return Domain
     */
    public function resolve(string $domain = null, string $section = self::ALL_DOMAINS): Domain
    {
        $this->validateSection($section);
        if (!$this->isMatchable($domain)) {
            return new Domain();
        }

        try {
            return new Domain($domain, $this->findPublicSuffix($domain, $section));
        } catch (Exception $e) {
            return new Domain($domain);
        }
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
     * Assert the section status.
     *
     * @param string $section
     *
     * @throws Exception if the submitted section is not supported
     */
    private function validateSection(string $section)
    {
        if (!$this->supports($section)) {
            throw new Exception(sprintf('%s is an unknown Public Suffix List section', $section));
        }
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
        $reverseLabels = array_reverse(explode('.', $this->normalizeDomain($domain)));
        $icann = $this->findPublicSuffixFromSection($reverseLabels, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $this->normalizePublicSuffix($icann, $domain);
        }

        $private = $this->findPublicSuffixFromSection($reverseLabels, self::PRIVATE_DOMAINS);
        if (count($private) > count($icann)) {
            return $this->normalizePublicSuffix($private, $domain);
        }

        if (self::ALL_DOMAINS === $section) {
            return $this->normalizePublicSuffix($icann, $domain);
        }

        return $this->normalizePublicSuffix(new PublicSuffix(), $domain);
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
    private function normalizeDomain(string $domain): string
    {
        if (false !== strpos($domain, '%')) {
            $domain = rawurldecode($domain);
        }

        try {
            return strtolower($this->idnToAscii($domain));
        } catch (Exception $e) {
            return '';
        }
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
        $rules = $this->rules[$section] ?? [];
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
     * Normalize the found Public Suffix against its domain name.
     *
     * @param PublicSuffix $publicSuffix
     * @param string       $domain
     *
     * @return PublicSuffix
     */
    private function normalizePublicSuffix(PublicSuffix $publicSuffix, string $domain): PublicSuffix
    {
        if (null === $publicSuffix->getContent()) {
            $labels = explode('.', $domain);
            $publicSuffix = new PublicSuffix($this->idnToAscii(array_pop($labels)));
        }

        if (false === strpos($domain, 'xn--')) {
            return $publicSuffix->toUnicode();
        }

        return $publicSuffix;
    }
}
