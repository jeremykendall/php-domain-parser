<?php

declare(strict_types=1);

namespace Pdp;

use JsonException;
use function array_reverse;
use function count;
use function fclose;
use function fopen;
use function implode;
use function is_array;
use function json_decode;
use function stream_get_contents;
use function substr;
use const JSON_THROW_ON_ERROR;

final class Rules implements PublicSuffixList
{
    /**
     * PSL rules as a multidimentional associative array.
     */
    private array $rules;

    private function __construct(array $rules)
    {
        $rules[EffectiveTLD::ICANN_DOMAINS] = $rules[EffectiveTLD::ICANN_DOMAINS] ?? [];
        $rules[EffectiveTLD::PRIVATE_DOMAINS] = $rules[EffectiveTLD::PRIVATE_DOMAINS] ?? [];

        if (!is_array($rules[EffectiveTLD::ICANN_DOMAINS]) || !is_array($rules[EffectiveTLD::PRIVATE_DOMAINS])) {
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
        static $converter;

        $converter = $converter ?? new PublicSuffixListConverter();

        return new self($converter->convert($content));
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
            $host = $exception->getDomain();
            if (null !== $host) {
                return new ResolvedDomain($host);
            }

            return new ResolvedDomain(new Domain($host));
        } catch (SyntaxError $exception) {
            return new ResolvedDomain(Domain::fromNull());
        }
    }

    /**
     * @param mixed $host the domain value
     */
    public function getCookieDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        $publicSuffix = $this->getPublicSuffix($domain, '');

        return new ResolvedDomain($domain, $publicSuffix);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getICANNDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        $publicSuffix = $this->getPublicSuffix($domain, EffectiveTLD::ICANN_DOMAINS);
        if (!$publicSuffix->isICANN()) {
            throw UnableToResolveDomain::dueToMissingPublicSuffix($domain, EffectiveTLD::ICANN_DOMAINS);
        }

        return new ResolvedDomain($domain, $publicSuffix);
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getPrivateDomain($host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        $publicSuffix = $this->getPublicSuffix($domain, EffectiveTLD::PRIVATE_DOMAINS);
        if (!$publicSuffix->isPrivate()) {
            throw UnableToResolveDomain::dueToMissingPublicSuffix($domain, EffectiveTLD::PRIVATE_DOMAINS);
        }

        return new ResolvedDomain($domain, $publicSuffix);
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
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->getDomain();
        }

        if (!($domain instanceof DomainName)) {
            $domain = new Domain($domain);
        }

        if ((2 > count($domain)) || ('.' === substr($domain->toString(), -1, 1))) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain;
    }

    /**
     * Returns the matched public suffix.
     */
    private function getPublicSuffix(DomainName $domain, string $section): EffectiveTLD
    {
        $icann = $this->getPublicSuffixFromSection($domain, EffectiveTLD::ICANN_DOMAINS);
        if (EffectiveTLD::ICANN_DOMAINS === $section) {
            return $icann;
        }

        $private = $this->getPublicSuffixFromSection($domain, EffectiveTLD::PRIVATE_DOMAINS);
        if (count($private) > count($icann)) {
            return $private;
        }

        if ('' === $section) {
            return $icann;
        }

        $publicSuffix = new Domain($domain->toAscii()->label(0), $domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption());

        return PublicSuffix::fromUnknown($publicSuffix);
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     */
    private function getPublicSuffixFromSection(DomainName $domain, string $section): EffectiveTLD
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
            $publicSuffix = new Domain($domain->toAscii()->label(0), $domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption());

            return PublicSuffix::fromUnknown($publicSuffix);
        }

        $publicSuffix = new Domain(
            implode('.', array_reverse($matches)),
            $domain->getAsciiIDNAOption(),
            $domain->getUnicodeIDNAOption()
        );

        if (PublicSuffix::PRIVATE_DOMAINS === $section) {
            return PublicSuffix::fromPrivate($publicSuffix);
        }

        return PublicSuffix::fromICANN($publicSuffix);
    }
}
