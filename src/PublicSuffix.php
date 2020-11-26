<?php

declare(strict_types=1);

namespace Pdp;

use function count;

final class PublicSuffix implements EffectiveTLD
{
    private DomainName $domain;

    private string $section;

    /**
     * @param mixed $domain the public suffix domain information
     */
    private function __construct($domain, string $section)
    {
        if ($domain instanceof ExternalDomainName) {
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
        return new self($domain, self::ICANN_DOMAINS);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromPrivate($domain): self
    {
        return new self($domain, self::PRIVATE_DOMAINS);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromIANA($domain): self
    {
        return new self($domain, self::IANA_DOMAINS);
    }

    /**
     * @param mixed $domain the public suffix domain information
     */
    public static function fromUnknown($domain): self
    {
        return new self($domain, '');
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

    public function isKnown(): bool
    {
        return '' !== $this->section;
    }

    public function isICANN(): bool
    {
        return self::ICANN_DOMAINS === $this->section;
    }

    public function isPrivate(): bool
    {
        return self::PRIVATE_DOMAINS === $this->section;
    }

    public function isIANA(): bool
    {
        return self::IANA_DOMAINS === $this->section;
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
}
