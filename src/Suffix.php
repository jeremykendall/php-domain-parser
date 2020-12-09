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

    /**
     * @param mixed $domain the public suffix domain information
     */
    private function __construct($domain, string $section)
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = Domain::fromIDNA2008($domain);
        }

        if ('' === $domain->label(0)) {
            throw SyntaxError::dueToInvalidPublicSuffix($domain);
        }

        if (null === $domain->value()) {
            $section = '';
        }

        $this->domain = $domain;
        $this->section = $section;
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['section']);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromICANN($domain): self
    {
        return new self($domain, self::ICANN);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromPrivate($domain): self
    {
        return new self($domain, self::PRIVATE);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromIANA($domain): self
    {
        return new self($domain, self::IANA);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromUnknown($domain): self
    {
        return new self($domain, '');
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
        return $this->domain->value();
    }

    public function value(): ?string
    {
        return $this->domain->value();
    }

    public function toString(): string
    {
        return $this->domain->toString();
    }

    public function toAscii(): self
    {
        $clone = clone $this;
        $clone->domain = $this->domain->toAscii();

        return $clone;
    }

    public function toUnicode(): self
    {
        $clone = clone $this;
        $clone->domain = $this->domain->toUnicode();

        return $clone;
    }

    public function normalize(DomainName $domain): self
    {
        $newSuffix = $domain->clear()->append($this->toUnicode());
        if ($domain->isAscii()) {
            $newSuffix = $newSuffix->toAscii();
        }

        $clone = clone $this;
        $clone->domain = $newSuffix;

        return $clone;
    }
}
