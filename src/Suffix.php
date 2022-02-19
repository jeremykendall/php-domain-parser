<?php

declare(strict_types=1);

namespace Pdp;

use Stringable;
use function count;
use function in_array;

final class Suffix implements EffectiveTopLevelDomain
{
    private const ICANN = 'ICANN';
    private const PRIVATE = 'PRIVATE';
    private const IANA = 'IANA';

    private function __construct(
        private DomainName $domain,
        private string $section
    ) {
    }

    /**
     * @param array{domain:DomainName, section:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['section']);
    }

    public static function fromICANN(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::ICANN);
        }

        return new self($domain, self::ICANN);
    }

    public static function fromPrivate(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::PRIVATE);
        }

        return new self($domain, self::PRIVATE);
    }

    public static function fromIANA(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 !== count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::IANA);
        }

        return new self($domain, self::IANA);
    }

    public static function fromUnknown(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        return new self(self::setDomainName($domain), '');
    }

    private static function setDomainName(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = Domain::fromIDNA2008($domain);
        }

        if ('' === $domain->label(0)) {
            throw SyntaxError::dueToInvalidSuffix($domain);
        }

        return $domain;
    }

    public function isKnown(): bool
    {
        return '' !== $this->section;
    }

    public function isIANA(): bool
    {
        return self::IANA === $this->section;
    }

    public function isPublicSuffix(): bool
    {
        return in_array($this->section, [self::ICANN, self::PRIVATE], true);
    }

    public function isICANN(): bool
    {
        return self::ICANN === $this->section;
    }

    public function isPrivate(): bool
    {
        return self::PRIVATE === $this->section;
    }

    public function domain(): DomainName
    {
        return $this->domain;
    }

    public function count(): int
    {
        return count($this->domain);
    }

    public function jsonSerialize(): ?string
    {
        return $this->domain->jsonSerialize();
    }

    public function value(): ?string
    {
        return $this->domain->value();
    }

    public function toString(): string
    {
        return $this->domain->toString();
    }

    public function toAscii(): static
    {
        $clone = clone $this;
        $clone->domain = $this->domain->toAscii();

        return $clone;
    }

    public function toUnicode(): static
    {
        $clone = clone $this;
        $clone->domain = $this->domain->toUnicode();

        return $clone;
    }

    public function normalize(DomainName $domain): self
    {
        $newDomain = $domain->clear()->append($this->toUnicode());
        if ($domain->isAscii()) {
            $newDomain = $newDomain->toAscii();
        }

        $clone = clone $this;
        $clone->domain = $newDomain;

        return $clone;
    }
}
