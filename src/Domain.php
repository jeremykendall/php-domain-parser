<?php

declare(strict_types=1);

namespace Pdp;

use Iterator;
use TypeError;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_slice;
use function array_unshift;
use function count;
use function explode;
use function filter_var;
use function gettype;
use function implode;
use function in_array;
use function is_object;
use function is_scalar;
use function ksort;
use function method_exists;
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

    /**
     * @var array<int, string>
     */
    private array $labels;

    private ?string $domain;

    private string $type;

    /**
     * @param null|mixed $domain
     */
    private function __construct(string $type, $domain)
    {
        $this->type = $type;
        $this->domain = $this->parseDomain($domain);
        $this->labels = null === $this->domain ? [] : array_reverse(explode('.', $this->domain));
    }

    /**
     * @param array{domain:string|null, type:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['type'], $properties['domain']);
    }

    /**
     * @param null|mixed $domain
     */
    public static function fromIDNA2003($domain): self
    {
        return new self(self::IDNA_2003, $domain);
    }

    /**
     * @param null|mixed $domain
     */
    public static function fromIDNA2008($domain): self
    {
        return new self(self::IDNA_2008, $domain);
    }

    /**
     * @param mixed $domain a domain
     */
    private function parseDomain($domain): ?string
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if ($domain instanceof Host) {
            return $this->parseValue($domain->toUnicode()->value());
        }

        return $this->parseValue($domain);
    }

    /**
     * Parse and format the domain to ensure it is valid.
     * Returns an array containing the formatted domain name labels
     * and the domain transitional information.
     *
     * For example: parse('wWw.uLb.Ac.be') should return ['www.ulb.ac.be', ['be', 'ac', 'ulb', 'www']];.
     *
     * @param mixed $domain a domain
     *
     * @throws SyntaxError If the host is not a domain
     * @throws SyntaxError If the domain is not a host
     */
    private function parseValue($domain): ?string
    {
        if (null === $domain) {
            return null;
        }

        if (is_object($domain) && method_exists($domain, '__toString')) {
            $domain = (string) $domain;
        }

        if (!is_scalar($domain)) {
            throw new TypeError('The domain must be a string, a stringable object, a Host object or NULL; `'.gettype($domain).'` given.');
        }

        $domain = (string) $domain;
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

        return $this->domainToUnicode($this->domainToAscii($formattedDomain));
    }

    private function domainToAscii(string $domain): string
    {
        $option = self::IDNA_2003 === $this->type ? Idna::IDNA2003_ASCII : Idna::IDNA2008_ASCII;

        return Idna::toAscii($domain, $option)->result();
    }

    private function domainToUnicode(string $domain): string
    {
        $option = self::IDNA_2003 === $this->type ? Idna::IDNA2003_UNICODE : Idna::IDNA2008_UNICODE;

        return Idna::toUnicode($domain, $option)->result();
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

    public function jsonSerialize(): ?string
    {
        return $this->domain;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function value(): ?string
    {
        return $this->domain;
    }

    public function toString(): string
    {
        return (string) $this->domain;
    }

    public function label(int $key): ?string
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

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toAscii(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = $this->domainToAscii($this->domain);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($this->type, $domain);
    }

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toUnicode(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = $this->domainToUnicode($this->domain);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($this->type, $domain);
    }

    /**
     * Filter a subdomain to update the domain part.
     *
     * @param string|object|null $domain a domain
     *
     * @throws TypeError if the domain can not be converted
     */
    private function normalize($domain): ?string
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

        if ((!is_string($domain) && !method_exists($domain, '__toString'))) {
            throw new TypeError('The label must be a '.Host::class.', a stringable object or a string, `'.gettype($domain).'` given.');
        }

        $domain = (string) $domain;
        if (null === $this->domain) {
            return $domain;
        }

        if (!$this->isAscii()) {
            return $this->domainToUnicode($domain);
        }

        return $this->domainToAscii($domain);
    }

    public function prepend($label): self
    {
        return $this->withLabel(count($this->labels), $label);
    }

    public function append($label): self
    {
        return $this->withLabel(- count($this->labels) - 1, $label);
    }

    public function withLabel(int $key, $label): self
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
