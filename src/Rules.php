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
use function is_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function stream_get_contents;
use function substr;
use const IDNA_DEFAULT;
use const JSON_ERROR_NONE;

final class Rules implements PublicSuffixList
{
    /**
     * PSL rules as a multidimentional associative array.
     */
    private array $rules;

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
        $data = json_decode($jsonString, true);
        $errorCode = json_last_error();
        if (JSON_ERROR_NONE !== $errorCode) {
            throw UnableToLoadPublicSuffixList::dueToInvalidJson($errorCode, json_last_error_msg());
        }

        if (!isset($data[EffectiveTLD::ICANN_DOMAINS], $data[EffectiveTLD::PRIVATE_DOMAINS])) {
            throw UnableToLoadPublicSuffixList::dueToInvalidHashMap();
        }

        if (!is_array($data[EffectiveTLD::ICANN_DOMAINS]) || !is_array($data[EffectiveTLD::PRIVATE_DOMAINS])) {
            throw UnableToLoadPublicSuffixList::dueToCorruptedSection();
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
    public function resolve(
        $host,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): ResolvedDomainName {
        try {
            return $this->getCookieDomain($host, $asciiIDNAOption, $unicodeIDNAOption);
        } catch (UnableToResolveDomain $exception) {
            if ($exception->hasDomain()) {
                /** @var Host */
                $host = $exception->getDomain();

                $host = $host->withAsciiIDNAOption($asciiIDNAOption)->withUnicodeIDNAOption($unicodeIDNAOption);

                return new ResolvedDomain($host);
            }

            return new ResolvedDomain(new Domain($host, $asciiIDNAOption, $unicodeIDNAOption));
        } catch (ExceptionInterface $exception) {
            return new ResolvedDomain(Domain::fromNull($asciiIDNAOption, $unicodeIDNAOption));
        }
    }

    /**
     * @param mixed $host the domain value
     */
    public function getCookieDomain(
        $host,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): ResolvedDomainName {
        $domain = $this->validateDomain($host, $asciiIDNAOption, $unicodeIDNAOption);

        return new ResolvedDomain($domain, $this->findPublicSuffix(
            $domain,
            '',
            $asciiIDNAOption,
            $unicodeIDNAOption
        ));
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getICANNDomain(
        $host,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): ResolvedDomainName {
        $domain = $this->validateDomain($host, $asciiIDNAOption, $unicodeIDNAOption);

        return new ResolvedDomain($domain, $this->findPublicSuffix(
            $domain,
            EffectiveTLD::ICANN_DOMAINS,
            $asciiIDNAOption,
            $unicodeIDNAOption
        ));
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function getPrivateDomain(
        $host,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): ResolvedDomainName {
        $domain = $this->validateDomain($host, $asciiIDNAOption, $unicodeIDNAOption);

        return new ResolvedDomain($domain, $this->findPublicSuffix(
            $domain,
            PublicSuffix::PRIVATE_DOMAINS,
            $asciiIDNAOption,
            $unicodeIDNAOption
        ));
    }

    /**
     * Assert the domain is valid and is resolvable.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    private function validateDomain($domain, int $asciiIDNAOption, int $unicodeIDNAOption): DomainName
    {
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->getDomain();
        }

        if (!($domain instanceof DomainName)) {
            $domain = new Domain($domain);
        }

        if ((2 > count($domain)) || ('.' === substr((string) $domain, -1, 1))) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain
            ->withAsciiIDNAOption($asciiIDNAOption)
            ->withUnicodeIDNAOption($unicodeIDNAOption);
    }

    /**
     * Returns the matched public suffix.
     */
    private function findPublicSuffix(
        DomainName $domain,
        string $section,
        int $asciiIDNAOption,
        int $unicodeIDNAOption
    ): PublicSuffix {
        $asciiDomain = $domain->toAscii();
        $icann = $this->findPublicSuffixFromSection($asciiDomain, EffectiveTLD::ICANN_DOMAINS, $asciiIDNAOption, $unicodeIDNAOption);
        if (EffectiveTLD::ICANN_DOMAINS === $section) {
            return $icann;
        }

        $private = $this->findPublicSuffixFromSection($asciiDomain, EffectiveTLD::PRIVATE_DOMAINS, $asciiIDNAOption, $unicodeIDNAOption);
        if (count($private) > count($icann)) {
            return $private;
        }

        if (EffectiveTLD::PRIVATE_DOMAINS === $section) {
            return PublicSuffix::fromUnknown($asciiDomain->label(0), $asciiIDNAOption, $unicodeIDNAOption);
        }

        return $icann;
    }

    /**
     * Returns the public suffix matched against a given PSL section.
     */
    private function findPublicSuffixFromSection(
        DomainName $domain,
        string $section,
        int $asciiIDNAOption,
        int $unicodeIDNAOption
    ): PublicSuffix {
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
            return PublicSuffix::fromUnknown($domain->label(0), $asciiIDNAOption, $unicodeIDNAOption);
        }

        $content = implode('.', array_reverse($matches));

        if (PublicSuffix::PRIVATE_DOMAINS === $section) {
            return PublicSuffix::fromPrivate($content, $asciiIDNAOption, $unicodeIDNAOption);
        }

        return PublicSuffix::fromICANN($content, $asciiIDNAOption, $unicodeIDNAOption);
    }
}
