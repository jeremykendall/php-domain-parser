<?php

declare(strict_types=1);

namespace Pdp;

use function count;
use function in_array;

final class Suffix implements EffectiveTopLevelDomain
{
    private const ICANN = 'ICANN';
    private const PRIVATE = 'PRIVATE';
    private const IANA = 'IANA';

    private DomainName $domain;

    private string $section;

    private function __construct(DomainName $domain, string $section)
    {
        $this->domain = $domain;
        $this->section = $section;
    }

    /**
     * @param array{domain:DomainName, section:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['section']);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromICANN($domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::ICANN);
        }

        return new self($domain, self::ICANN);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromPrivate($domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::PRIVATE);
        }

        return new self($domain, self::PRIVATE);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromIANA($domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 !== count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::IANA);
        }

        return new self($domain, self::IANA);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromUnknown($domain): self
    {
        return new self(self::setDomainName($domain), '');
    }

    /**
     * @param mixed $domain The domain to be resolved
     */
    private static function setDomainName($domain): DomainName
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

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toAscii(): self
    {
        $clone = clone $this;
        $clone->domain = $this->domain->toAscii();

        return $clone;
    }

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toUnicode(): self
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
