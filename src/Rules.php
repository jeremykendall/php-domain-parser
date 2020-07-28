<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use function array_reverse;
use function count;
use function fclose;
use function fopen;
use function implode;
use function in_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function stream_get_contents;
use const IDNA_DEFAULT;
use const JSON_ERROR_NONE;

/**
 * A class to resolve domain name against the Public Suffix list.
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Rules implements PublicSuffixListInterface
{
    private const PSL_SECTION = [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, ''];

    /**
     * PSL rules as a multidimentional associative array.
     */
    private array $rules;

    private int $asciiIDNAOption;

    private int $unicodeIDNAOption;

    private function __construct(
        array $rules,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ) {
        $this->rules = $rules;
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadRules If the rules can not be loaded from the path
     */
    public static function createFromPath(
        string $path,
        $context = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): self {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        $resource = @fopen(...$args);
        if (false === $resource) {
            throw UnableToLoadRules::dueToInvalidPath($path);
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return self::createFromString($content, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * Returns a new instance from a string.
     */
    public static function createFromString(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): self {
        static $converter;

        $converter = $converter ?? new RulesConverter();

        return new self($converter->convert($content), $asciiIDNAOption, $unicodeIDNAOption);
    }

    public static function createFromJsonString(
        string $jsonString,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): self {
        $data = json_decode($jsonString, true);
        $errorCode = json_last_error();
        if (JSON_ERROR_NONE !== $errorCode) {
            throw UnableToLoadRules::dueToInvalidJson($errorCode, json_last_error_msg());
        }

        return new self($data, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['rules'],
            $properties['asciiIDNAOption'] ?? IDNA_DEFAULT,
            $properties['unicodeIDNAOption'] ?? IDNA_DEFAULT
        );
    }

    public function getAsciiIDNAOption(): int
    {
        return $this->asciiIDNAOption;
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }

    public function jsonSerialize(): array
    {
        return $this->rules;
    }

    /**
     * Determines the public suffix for a given domain.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws UnableToResolveDomain If the PublicSuffix can not be resolve.
     */
    public function getPublicSuffix($domain, string $section = ''): PublicSuffixInterface
    {
        if (!$domain instanceof ResolvableHostInterface) {
            $domain = new ResolvableDomain(new Domain($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption));
        }

        if (!$domain->getDomain()->isResolvable()) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        $publicSuffix = $this->findPublicSuffix($domain, $this->validateSection($section));

        return $domain->resolve($publicSuffix)->getPublicSuffix();
    }

    /**
     * Determines the public suffix for a given domain against the PSL rules for cookie domain detection..
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws UnableToResolveDomain If the PublicSuffix can not be resolve.
     */
    public function getCookieEffectiveTLD($domain): PublicSuffixInterface
    {
        return $this->getPublicSuffix($domain, '');
    }

    /**
     * Determines the public suffix for a given domain against the PSL rules for ICANN domain detection..
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws UnableToResolveDomain If the PublicSuffix can not be resolve.
     */
    public function getICANNEffectiveTLD($domain): PublicSuffixInterface
    {
        return $this->getPublicSuffix($domain, self::ICANN_DOMAINS);
    }

    /**
     * Determines the public suffix for a given domain against the PSL rules for private domain detection..
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws UnableToResolveDomain If the PublicSuffix can not be resolve.
     */
    public function getPrivateEffectiveTLD($domain): PublicSuffixInterface
    {
        return $this->getPublicSuffix($domain, self::PRIVATE_DOMAINS);
    }

    /**
     * Returns PSL info for a given domain.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolve($domain, string $section = ''): ResolvableHostInterface
    {
        $section = $this->validateSection($section);
        try {
            if ('' === $section) {
                return $this->resolveCookieDomain($domain);
            }

            if (self::ICANN_DOMAINS === $section) {
                return $this->resolveICANNDomain($domain);
            }

            return $this->resolvePrivateDomain($domain);
        } catch (UnableToResolveDomain $exception) {
            if ($exception->hasDomain()) {
                /** @var HostInterface */
                $domain = $exception->getDomain();

                return new ResolvableDomain($domain);
            }

            return new ResolvableDomain(new Domain($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption));
        } catch (ExceptionInterface $exception) {
            return new ResolvableDomain(Domain::fromNull($this->asciiIDNAOption, $this->unicodeIDNAOption));
        }
    }

    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @param mixed $domain the domain value
     */
    public function resolveCookieDomain($domain): ResolvableHostInterface
    {
        $domain = $this->validateDomain($domain);

        return $domain->resolve($this->findPublicSuffix($domain, ''));
    }

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolveICANNDomain($domain): ResolvableHostInterface
    {
        $domain = $this->validateDomain($domain);

        return $domain->resolve($this->findPublicSuffix($domain, self::ICANN_DOMAINS));
    }

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolvePrivateDomain($domain): ResolvableHostInterface
    {
        $domain = $this->validateDomain($domain);

        return $domain->resolve($this->findPublicSuffix($domain, self::PRIVATE_DOMAINS));
    }

    /**
     * Assert the domain is valid and is resolvable.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws InvalidDomain         if the domain is invalid
     * @throws UnableToResolveDomain if the domain is not resolvable
     */
    private function validateDomain($domain): ResolvableHostInterface
    {
        if (!($domain instanceof ResolvableHostInterface)) {
            $domain = new ResolvableDomain(new Domain($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption), null);
        }

        if (!$domain->getDomain()->isResolvable()) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain;
    }

    /**
     * Assert the section status.
     *
     * @throws ExceptionInterface if the submitted section is not supported
     */
    private function validateSection(string $section): string
    {
        if (in_array($section, self::PSL_SECTION, true)) {
            return $section;
        }

        throw UnableToResolveDomain::dueToUnSupportedSection($section);
    }

    /**
     * Returns the matched public suffix.
     */
    private function findPublicSuffix(ResolvableHostInterface $domain, string $section): PublicSuffixInterface
    {
        $asciiDomain = $domain->getDomain();
        /** @var DomainInterface $asciiDomain */
        $asciiDomain = $asciiDomain->toAscii();
        $icann = $this->findPublicSuffixFromSection($asciiDomain, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $icann;
        }

        $private = $this->findPublicSuffixFromSection($asciiDomain, self::PRIVATE_DOMAINS);
        if (count($private) > count($icann)) {
            return $private;
        }

        if (self::PRIVATE_DOMAINS === $section) {
            return PublicSuffix::fromUnknownSection($asciiDomain->label(0), $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        return $icann;
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     */
    private function findPublicSuffixFromSection(DomainInterface $domain, string $section): PublicSuffixInterface
    {
        $rules = $this->rules[$section] ?? [];
        $matches = [];
        foreach ($domain as $label) {
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

        if ([] === $matches) {
            return PublicSuffix::fromUnknownSection($domain->label(0), $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        $content = implode('.', array_reverse($matches));

        if (self::PRIVATE_DOMAINS === $section) {
            return PublicSuffix::fromPrivateSection($content, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        return PublicSuffix::fromICANNSection($content, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withAsciiIDNAOption(int $asciiIDNAOption): self
    {
        if ($asciiIDNAOption === $this->asciiIDNAOption) {
            return $this;
        }

        $clone = clone $this;
        $clone->asciiIDNAOption = $asciiIDNAOption;

        return $clone;
    }

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withUnicodeIDNAOption(int $unicodeIDNAOption): self
    {
        if ($unicodeIDNAOption === $this->unicodeIDNAOption) {
            return $this;
        }

        $clone = clone $this;
        $clone->unicodeIDNAOption = $unicodeIDNAOption;

        return $clone;
    }
}
