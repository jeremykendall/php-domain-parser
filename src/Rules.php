<?php

declare(strict_types=1);

namespace Pdp;

use SplTempFileObject;
use TypeError;
use function array_pop;
use function count;
use function explode;
use function fclose;
use function fopen;
use function gettype;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function stream_get_contents;
use function strpos;
use function substr;

final class Rules implements PublicSuffixList
{
    private const ICANN_DOMAINS = 'ICANN_DOMAINS';
    private const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    private const REGEX_PSL_SECTION = ',^// ===(?<point>BEGIN|END) (?<type>ICANN|PRIVATE) DOMAINS===,';
    private const PSL_SECTION = [
        'ICANN' => [
            'BEGIN' => self::ICANN_DOMAINS,
            'END' => '',
        ],
        'PRIVATE' => [
            'BEGIN' => self::PRIVATE_DOMAINS,
            'END' => '',
        ],
    ];

    /**
     * PSL rules as a multidimensional associative array.
     *
     * @var array<string, array<array>>
     */
    private array $rules;

    /**
     * @param array<string, array<array>> $rules
     */
    private function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadPublicSuffixList If the rules can not be loaded from the path
     */
    public static function fromPath(string $path, $context = null): self
    {
        $resource = self::getResource($path, $context);
        if (false === $resource) {
            throw UnableToLoadPublicSuffixList::dueToInvalidPath($path);
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return self::fromString($content);
    }

    /**
     * @param null|resource $context
     *
     * @return false|resource
     */
    private static function getResource(string $path, $context = null)
    {
        if (null === $context) {
            return @fopen($path, 'r');
        }

        return @fopen($path, 'r', false, $context);
    }

    /**
     * Returns a new instance from a string.
     *
     * @param object|string $content a string or an object which exposes the __toString method
     */
    public static function fromString($content): self
    {
        if (is_object($content) && method_exists($content, '__toString')) {
            $content = (string) $content;
        }

        if (!is_string($content)) {
            throw new TypeError('The content to be converted should be a string or a Stringable object, `'.gettype($content).'` given.');
        }

        return new self(self::parse($content));
    }

    /**
     * Convert the Public Suffix List into an associative, multidimensional array.
     *
     * @return array<string, array<array>>
     */
    private static function parse(string $content): array
    {
        $rules = [self::ICANN_DOMAINS => [], self::PRIVATE_DOMAINS => []];
        $section = '';
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        /** @var string $line */
        foreach ($file as $line) {
            $section = self::getSection($section, $line);
            if (in_array($section, [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS], true) && false === strpos($line, '//')) {
                $rules[$section] = self::addRule($rules[$section], explode('.', $line));
            }
        }

        return $rules;
    }

    /**
     * Returns the section type for a given line.
     */
    private static function getSection(string $section, string $line): string
    {
        if (1 === preg_match(self::REGEX_PSL_SECTION, $line, $matches)) {
            return self::PSL_SECTION[$matches['type']][$matches['point']];
        }

        return $section;
    }

    /**
     * Recursive method to build the array representation of the Public Suffix List.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * @see https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param array<array>  $list      Initially an empty array, this eventually becomes the array representation of a
     *                                 Public Suffix List section
     * @param array<string> $ruleParts One line (rule) from the Public Suffix List exploded on '.', or the remaining
     *                                 portion of that array during recursion
     *
     * @throws UnableToLoadPublicSuffixList if the domain name can not be converted using IDN to ASCII algorithm
     *
     * @return array<array>
     */
    private static function addRule(array $list, array $ruleParts): array
    {
        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."
        try {
            /** @var string $line */
            $line = array_pop($ruleParts);
            $rule = Idna::toAscii($line, Idna::IDNA2008_ASCII)->result();
        } catch (CannotProcessHost $exception) {
            throw UnableToLoadPublicSuffixList::dueToInvalidRule($line ?? null, $exception);
        }

        $isDomain = true;
        if (0 === strpos($rule, '!')) {
            $rule = substr($rule, 1);
            $isDomain = false;
        }

        $list[$rule] = $list[$rule] ?? ($isDomain ? [] : ['!' => '']);
        if ($isDomain && [] !== $ruleParts) {
            $list[$rule] = self::addRule($list[$rule], $ruleParts);
        }

        return $list;
    }

    /**
     * @param array{rules:array{ICANN_DOMAINS:array,PRIVATE_DOMAINS:array}} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['rules']);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function resolve($host): ResolvedDomainName
    {
        try {
            return $this->getCookieDomain($host);
        } catch (UnableToResolveDomain $exception) {
            return ResolvedDomain::fromUnknown($exception->domain());
        } catch (SyntaxError $exception) {
            return ResolvedDomain::fromUnknown(Domain::fromIDNA2008(null));
        }
    }

    /**
     * @param mixed $host the domain value
     */
    public function getCookieDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        [$length, $section] = $this->getEffectiveTopLevelDomain($domain, '');
        if (self::ICANN_DOMAINS === $section) {
            return ResolvedDomain::fromICANN($domain, $length);
        }

        if (self::PRIVATE_DOMAINS === $section) {
            return ResolvedDomain::fromPrivate($domain, $length);
        }

        return ResolvedDomain::fromUnknown($domain, $length);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getICANNDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        [$length, $section] = $this->getEffectiveTopLevelDomain($domain, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS !== $section) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'ICANN');
        }

        return ResolvedDomain::fromICANN($domain, $length);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getPrivateDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        [$length, $section] = $this->getEffectiveTopLevelDomain($domain, self::PRIVATE_DOMAINS);
        if (self::PRIVATE_DOMAINS !== $section) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'private');
        }

        return ResolvedDomain::fromPrivate($domain, $length);
    }

    /**
     * Assert the domain is valid and is resolvable.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws SyntaxError           If the domain is invalid
     * @throws UnableToResolveDomain If the domain can not be resolved
     */
    private function validateDomain($domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = Domain::fromIDNA2008($domain);
        }

        if ('' === $domain->label(0)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain;
    }

    /**
     * Returns the matched public suffix.
     *
     * @return array{0:int, 1:string}
     */
    private function getEffectiveTopLevelDomain(DomainName $domain, string $section): array
    {
        $icann = $this->getEffectiveTopLevelDomainFromSection($domain, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $icann;
        }

        $private = $this->getEffectiveTopLevelDomainFromSection($domain, self::PRIVATE_DOMAINS);
        if ($private[0] > $icann[0]) {
            return $private;
        }

        if ('' === $section) {
            return $icann;
        }

        return [1, ''];
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     *
     * @return array{0:int, 1:string}
     */
    private function getEffectiveTopLevelDomainFromSection(DomainName $domain, string $section): array
    {
        $rules = $this->rules[$section];
        $matches = [];
        foreach ($domain->toAscii() as $label) {
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
            return [1, ''];
        }

        return [count($matches), $section];
    }
}
