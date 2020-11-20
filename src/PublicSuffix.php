<?php

declare(strict_types=1);

namespace Pdp;

use function count;

final class PublicSuffix implements EffectiveTLD
{
    private DomainName $domain;

    private string $section;

    private function __construct(DomainName $domain, string $section)
    {
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

    public static function fromICANN(DomainName $domain): self
    {
        return new self($domain, self::ICANN_DOMAINS);
    }

    public static function fromPrivate(DomainName $domain): self
    {
        return new self($domain, self::PRIVATE_DOMAINS);
    }

    public static function fromUnknown(DomainName $domain): self
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
