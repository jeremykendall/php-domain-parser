<?php

declare(strict_types=1);

namespace Pdp;

use function count;
use const IDNA_DEFAULT;

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

    public static function fromNull(): self
    {
        return new self(Domain::fromNull(), '');
    }

    public function getDomain(): DomainName
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

    public function getAsciiIDNAOption(): int
    {
        return $this->domain->getAsciiIDNAOption();
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->domain->getUnicodeIDNAOption();
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

    public function withValue(?string $domain, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        $newDomain = $this->domain->withValue($domain, $asciiIDNAOption, $unicodeIDNAOption);
        if ($newDomain == $this->domain) {
            return $this;
        }

        return new self($newDomain, $this->section);
    }
}
