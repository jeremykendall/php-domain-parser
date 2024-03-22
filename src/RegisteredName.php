<?php

declare(strict_types=1);

namespace Pdp;

use Iterator;
use Stringable;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_slice;
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

final class RegisteredName implements DomainName
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

    /** @var list<string> */
    private readonly array $labels;
    private readonly ?string $domain;

    /**
     * @throws CannotProcessHost
     */
    private function __construct(private readonly string $type, DomainNameProvider|Host|Stringable|string|int|null $domain)
    {
        $this->domain = $this->parseDomain($domain);
        $this->labels = null === $this->domain ? [] : array_reverse(explode('.', $this->domain));
    }

    /**
     * @param array{domain:string|null, type:string} $properties
     *
     * @throws CannotProcessHost
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['type'], $properties['domain']);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIDNA2003(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        return new self(self::IDNA_2003, $domain);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIDNA2008(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        return new self(self::IDNA_2008, $domain);
    }

    /**
     * @throws CannotProcessHost
     */
    private function parseDomain(DomainNameProvider|Host|Stringable|string|int|null $domain): ?string
    {
        return $this->parseValue(match (true) {
            $domain instanceof DomainNameProvider => $domain->domain()->value(),
            $domain instanceof Host => $domain->toUnicode()->value(),
            default => $domain,
        });
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
    private function parseValue(Stringable|string|int|null $domain): ?string
    {
        if (null === $domain) {
            return null;
        }

        if ($domain instanceof Stringable) {
            $domain = (string) $domain;
        }

        $domain = (string) $domain;
        if ('' === $domain) {
            return '';
        }

        $res = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (false !== $res) {
            return $res;
        }

        $formattedDomain = rawurldecode($domain);
        return match (true) {
            1 === preg_match(self::REGEXP_REGISTERED_NAME, $formattedDomain) => strtolower($formattedDomain),
            // a domain name can not contain URI delimiters or space
            1 === preg_match(self::REGEXP_URI_DELIMITERS, $formattedDomain) => throw SyntaxError::dueToInvalidCharacters($domain),
            // if the domain name does not contain UTF-8 chars then it is malformed
            1 !== preg_match(self::REGEXP_IDN_PATTERN, $formattedDomain) => throw SyntaxError::dueToMalformedValue($domain),
            default => $this->domainToUnicode($this->domainToAscii($formattedDomain)),
        };
    }

    private function domainToAscii(string $domain): string
    {
        return Idna::toAscii(
            $domain,
            self::IDNA_2003 === $this->type ? Idna::IDNA2003_ASCII : Idna::IDNA2008_ASCII
        )->result();
    }

    private function domainToUnicode(string $domain): string
    {
        return Idna::toUnicode(
            $domain,
            self::IDNA_2003 === $this->type ? Idna::IDNA2003_UNICODE : Idna::IDNA2008_UNICODE
        )->result();
    }

    /**
     * @return Iterator<string>
     */
    public function getIterator(): Iterator
    {
        yield from $this->labels;
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
    public function keys(?string $label = null): array
    {
        if (null === $label) {
            return array_keys($this->labels);
        }

        return array_keys($this->labels, $label, true);
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return $this->labels;
    }

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
     */
    private function normalize(DomainNameProvider|Host|Stringable|string|int|null $domain): ?string
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if ($domain instanceof Host) {
            $domain = $domain->value();
        }

        if (null === $domain) {
            return null;
        }

        $domain = (string) $domain;

        return match (true) {
            null === $this->domain => $domain,
            $this->isAscii() => $this->domainToAscii($domain),
            default => $this->domainToUnicode($domain),
        };
    }

    /**
     * @throws CannotProcessHost
     */
    public function prepend(DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        return $this->withLabel(count($this->labels), $label);
    }

    /**
     * @throws CannotProcessHost
     */
    public function append(DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        return $this->withLabel(- count($this->labels) - 1, $label);
    }

    public function withLabel(int $key, DomainNameProvider|Host|Stringable|string|int|null $label): self
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

    public function withoutLabel(int ...$keys): self
    {
        $nbLabels = count($this->labels);
        foreach ($keys as &$offset) {
            if (- $nbLabels > $offset || $nbLabels - 1 < $offset) {
                throw SyntaxError::dueToInvalidLabelKey($this, $offset);
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

        if ($labels === $this->labels) {
            return $this;
        }

        return new self($this->type, [] === $labels ? null : implode('.', array_reverse($labels)));
    }

    /**
     * @throws CannotProcessHost
     */
    public function clear(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        return new self($this->type, null);
    }

    /**
     * @throws CannotProcessHost
     */
    public function slice(int $offset, ?int $length = null): self
    {
        $nbLabels = count($this->labels);
        if ($offset < - $nbLabels || $offset > $nbLabels) {
            throw SyntaxError::dueToInvalidLabelKey($this, $offset);
        }

        $labels = array_slice($this->labels, $offset, $length, true);
        if ($labels === $this->labels) {
            return $this;
        }

        return new self($this->type, [] === $labels ? null : implode('.', array_reverse($labels)));
    }
}
