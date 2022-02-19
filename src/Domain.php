<?php

declare(strict_types=1);

namespace Pdp;

use Iterator;
use Stringable;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_slice;
use function array_unshift;
use function count;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function ksort;
use function preg_match;
use function rawurldecode;
use function strtolower;
use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

final class Domain implements DomainName
{
    private const IDNA_2003 = 'IDNA_2003';
    private const IDNA_2008 = 'IDNA_2008';
    private const REGEXP_IDN_PATTERN = '/[^\x20-\x7f]/';

    // Note that unreserved is purposely missing . as it is used to separate labels.
    private const REGEXP_REGISTERED_NAME = '/(?(DEFINE)
        (?<unreserved>[a-z0-9_~\-])
        (?<sub_delims>[!$&\'()*+,;=])
        (?<encoded>%[A-F0-9]{2})
        (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded)){1,63})
    )
    ^(?:(?&reg_name)\.){0,126}(?&reg_name)\.?$/ix';

    private const REGEXP_URI_DELIMITERS = '/[:\/?#\[\]@ ]/';

    /** @var array<int, string> */
    private array $labels;
    private string|null $domain;

    private function __construct(private string $type, DomainNameProvider|DomainName|Host|Stringable|string|null $domain)
    {
        $this->domain = self::parseDomain($domain, $this->type);
        $this->labels = null === $this->domain ? [] : array_reverse(explode('.', $this->domain));
    }

    private static function parseDomain(DomainNameProvider|DomainName|Host|Stringable|string|null $domain, string $type): string|null
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if ($domain instanceof Host) {
            return self::parseValue($domain->toUnicode()->value(), $type);
        }

        return self::parseValue($domain, $type);
    }

    /**
     * Parse and format the domain to ensure it is valid.
     * Returns an array containing the formatted domain name labels
     * and the domain transitional information.
     *
     * For example: parse('wWw.uLb.Ac.be') should return ['www.ulb.ac.be', ['be', 'ac', 'ulb', 'www']];.
     *
     * @throws SyntaxError If the host is not a domain
     * @throws SyntaxError If the domain is not a host
     */
    private static function parseValue(Stringable|string|null $domain, string $type): string|null
    {
        if (null === $domain) {
            return null;
        }

        if ($domain instanceof Stringable) {
            $domain = (string) $domain;
        }

        if ('' === $domain) {
            return '';
        }

        $res = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (false !== $res) {
            throw SyntaxError::dueToUnsupportedType($domain);
        }

        $formattedDomain = rawurldecode($domain);
        if (1 === preg_match(self::REGEXP_REGISTERED_NAME, $formattedDomain)) {
            return strtolower($formattedDomain);
        }

        // a domain name can not contains URI delimiters or space
        if (1 === preg_match(self::REGEXP_URI_DELIMITERS, $formattedDomain)) {
            throw SyntaxError::dueToInvalidCharacters($domain);
        }

        // if the domain name does not contains UTF-8 chars then it is malformed
        if (1 !== preg_match(self::REGEXP_IDN_PATTERN, $formattedDomain)) {
            throw SyntaxError::dueToMalformedValue($domain);
        }

        return self::domainToUnicode($type, self::domainToAscii($type, $formattedDomain));
    }

    private static function domainToAscii(string $type, string $domain): string
    {
        return Idna::toAscii($domain, self::IDNA_2003 === $type ? Idna::IDNA2003_ASCII : Idna::IDNA2008_ASCII)->result();
    }

    private static function domainToUnicode(string $type, string $domain): string
    {
        return Idna::toUnicode($domain, self::IDNA_2003 === $type ? Idna::IDNA2003_UNICODE : Idna::IDNA2008_UNICODE)->result();
    }

    /**
     * @param array{domain:string|null, type:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['type'], $properties['domain']);
    }

    public static function fromIDNA2003(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        return new self(self::IDNA_2003, $domain);
    }

    public static function fromIDNA2008(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        return new self(self::IDNA_2008, $domain);
    }

    /**
     * @return Iterator<string>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->labels as $label) {
            yield $label;
        }
    }

    public function isAscii(): bool
    {
        return null === $this->domain || 1 !== preg_match(self::REGEXP_IDN_PATTERN, $this->domain);
    }

    public function jsonSerialize(): string|null
    {
        return $this->domain;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function value(): string|null
    {
        return $this->domain;
    }

    public function toString(): string
    {
        return (string) $this->domain;
    }

    public function label(int $key): string|null
    {
        if ($key < 0) {
            $key += count($this->labels);
        }

        return $this->labels[$key] ?? null;
    }

    /**
     * @return list<int>
     */
    public function keys(string $label = null): array
    {
        if (null === $label) {
            return array_keys($this->labels);
        }

        return array_keys($this->labels, $label, true);
    }

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return $this->labels;
    }

    public function toAscii(): static
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = self::domainToAscii($this->type, $this->domain);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($this->type, $domain);
    }

    public function toUnicode(): static
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = self::domainToUnicode($this->type, $this->domain);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($this->type, $domain);
    }

    /**
     * Filter a subdomain to update the domain part.
     */
    private function normalize(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): string|null
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if ($domain instanceof Host) {
            $domain = $domain->value();
        }

        if (null === $domain) {
            return $domain;
        }

        $domain = (string) $domain;
        if (null === $this->domain) {
            return $domain;
        }

        if (!$this->isAscii()) {
            return self::domainToUnicode($this->type, $domain);
        }

        return self::domainToAscii($this->type, $domain);
    }

    /**
     * @throws CannotProcessHost
     */
    public function prepend(DomainNameProvider|DomainName|Host|Stringable|string|null $label): self
    {
        return $this->withLabel(count($this->labels), $label);
    }

    /**
     * @throws CannotProcessHost
     */
    public function append(DomainNameProvider|DomainName|Host|Stringable|string|null $label): self
    {
        return $this->withLabel(- count($this->labels) - 1, $label);
    }

    /**
     * @throws CannotProcessHost
     */
    public function withLabel(int $key, DomainNameProvider|DomainName|Host|Stringable|string|null $label): self
    {
        $nbLabels = count($this->labels);
        if ($key < - $nbLabels - 1 || $key > $nbLabels) {
            throw SyntaxError::dueToInvalidLabelKey($this, $key);
        }

        if (0 > $key) {
            $key = $nbLabels + $key;
        }

        $label = $this->normalize($label);

        if (($this->labels[$key] ?? null) === $label) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;
        ksort($labels);

        return new self($this->type, implode('.', array_reverse($labels)));
    }

    /**
     * @throws CannotProcessHost
     */
    public function withoutLabel(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nbLabels = count($this->labels);
        foreach ($keys as &$offset) {
            if (- $nbLabels > $offset || $nbLabels - 1 < $offset) {
                throw SyntaxError::dueToInvalidLabelKey($this, $key);
            }

            if (0 > $offset) {
                $offset += $nbLabels;
            }
        }
        unset($offset);

        $deletedKeys = array_keys(array_count_values($keys));
        $labels = [];
        foreach ($this->labels as $offset => $label) {
            if (!in_array($offset, $deletedKeys, true)) {
                $labels[] = $label;
            }
        }

        $clone = clone $this;
        $clone->labels = $labels;
        $clone->domain = [] === $labels ? null : implode('.', array_reverse($labels));

        return $clone;
    }

    public function clear(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        return new self($this->type, null);
    }

    public function slice(int $offset, int $length = null): self
    {
        $nbLabels = count($this->labels);
        if ($offset < - $nbLabels || $offset > $nbLabels) {
            throw SyntaxError::dueToInvalidLabelKey($this, $offset);
        }

        $labels = array_slice($this->labels, $offset, $length, true);
        if ($labels === $this->labels) {
            return $this;
        }

        $clone = clone $this;
        $clone->labels = $labels;
        $clone->domain = [] === $labels ? null : implode('.', array_reverse($labels));

        return $clone;
    }
}
