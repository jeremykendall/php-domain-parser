<?php

declare(strict_types=1);

namespace Pdp;

use JsonException;
use SplTempFileObject;
use TypeError;
use function array_pop;
use function array_reverse;
use function count;
use function explode;
use function fclose;
use function fopen;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function method_exists;
use function preg_match;
use function stream_get_contents;
use function strpos;
use function substr;
use const JSON_THROW_ON_ERROR;

final class Rules implements PublicSuffixList
{
    private const ICANN_DOMAINS = 'ICANN_DOMAINS';

    private const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

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

    private const REGEX_PSL_SECTION = ',^// ===(?<point>BEGIN|END) (?<type>ICANN|PRIVATE) DOMAINS===,';

    /**
     * PSL rules as a multidimentional associative array.
     */
    private array $rules;

    private function __construct(array $rules)
    {
        $rules[self::ICANN_DOMAINS] = $rules[self::ICANN_DOMAINS] ?? [];
        $rules[self::PRIVATE_DOMAINS] = $rules[self::PRIVATE_DOMAINS] ?? [];

        if (!is_array($rules[self::ICANN_DOMAINS]) || !is_array($rules[self::PRIVATE_DOMAINS])) {
            throw UnableToLoadPublicSuffixList::dueToCorruptedSection();
        }

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
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        $resource = @fopen(...$args);
        if (false === $resource) {
            throw UnableToLoadPublicSuffixList::dueToInvalidPath($path);
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return self::fromString($content);
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
     */
    private static function parse(string $content): array
    {
        $rules = [];
        $section = '';
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        /** @var string $line */
        foreach ($file as $line) {
            $section = self::getSection($section, $line);
            if ('' !== $section && false === strpos($line, '//')) {
                $rules[$section] = $rules[$section] ?? [];
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
     * @param array $list      Initially an empty array, this eventually
     *                         becomes the array representation of a Public Suffix List section
     * @param array $ruleParts One line (rule) from the Public Suffix List
     *                         exploded on '.', or the remaining portion of that array during recursion
     *
     * @throws UnableToLoadPublicSuffixList if the domain name can not be converted using IDN to ASCII algorithm
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
            $rule = IntlIdna::toAscii($line, IntlIdna::IDNA2008_ASCII_OPTIONS);
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

    public static function fromJsonString(string $jsonString): self
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UnableToLoadPublicSuffixList::dueToInvalidJson($exception);
        }

        return new self($data);
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['rules']);
    }

    public function jsonSerialize(): array
    {
        return $this->rules;
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function resolve($host): ResolvedDomainName
    {
        try {
            return $this->getCookieDomain($host);
        } catch (UnableToResolveDomain $exception) {
            return ResolvedDomain::fromHost($exception->getDomain());
        } catch (SyntaxError $exception) {
            return ResolvedDomain::fromHost(Domain::fromIDNA2008(null));
        }
    }

    /**
     * @param mixed $host the domain value
     */
    public function getCookieDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);

        return ResolvedDomain::fromHost($domain, $this->getEffectiveTLD($domain, ''));
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getICANNDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        $publicSuffix = $this->getEffectiveTLD($domain, self::ICANN_DOMAINS);
        if (!$publicSuffix->isICANN()) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'ICANN');
        }

        return ResolvedDomain::fromHost($domain, $publicSuffix);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getPrivateDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        $publicSuffix = $this->getEffectiveTLD($domain, self::PRIVATE_DOMAINS);
        if (!$publicSuffix->isPrivate()) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'private');
        }

        return ResolvedDomain::fromHost($domain, $publicSuffix);
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

        if (!($domain instanceof DomainName)) {
            $domain = Domain::fromIDNA2008($domain);
        }

        return $domain;
    }

    /**
     * Returns the matched public suffix.
     */
    private function getEffectiveTLD(DomainName $domain, string $section): EffectiveTLD
    {
        $icann = $this->getEffectiveTLDFromSection($domain, self::ICANN_DOMAINS);
        if (self::ICANN_DOMAINS === $section) {
            return $icann;
        }

        $private = $this->getEffectiveTLDFromSection($domain, self::PRIVATE_DOMAINS);
        if (count($private) > count($icann)) {
            return $private;
        }

        if ('' === $section) {
            return $icann;
        }

        $publicSuffix = $domain->clear()->withLabel(0, $domain->label(0));

        return Suffix::fromUnknown($publicSuffix);
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     */
    private function getEffectiveTLDFromSection(DomainName $domain, string $section): EffectiveTLD
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
            $label = $domain->label(0);
            $publicSuffix = $domain->clear();
            if ('' === $label) {
                return Suffix::fromUnknown($publicSuffix);
            }

            return Suffix::fromUnknown($publicSuffix->append($label));
        }

        $publicSuffix = $domain->clear()->append(implode('.', array_reverse($matches)));
        if (self::PRIVATE_DOMAINS === $section) {
            return Suffix::fromPrivate($publicSuffix);
        }

        return Suffix::fromICANN($publicSuffix);
    }
}
