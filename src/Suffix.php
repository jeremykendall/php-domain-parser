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
    private const UNKNOWN = 'UNKNOWN';

    private function __construct(
        private readonly DomainName $domain,
        private readonly string $section
    ) {
    }

    /**
     * @param array{domain:DomainName, section:Suffix::ICANN|Suffix::PRIVATE|Suffix::IANA|Suffix::UNKNOWN} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['section']);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromICANN(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::ICANN);
        }

        return new self($domain, self::ICANN);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromPrivate(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 > count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::PRIVATE);
        }

        return new self($domain, self::PRIVATE);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIANA(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        $domain = self::setDomainName($domain);
        if (1 !== count($domain)) {
            throw SyntaxError::dueToInvalidSuffix($domain, self::IANA);
        }

        return new self($domain, self::IANA);
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromUnknown(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        return new self(self::setDomainName($domain), self::UNKNOWN);
    }

    /**
     * @throws CannotProcessHost
     */
    private static function setDomainName(int|DomainNameProvider|Host|string|Stringable|null $domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = RegisteredName::fromIDNA2008($domain);
        }

        if ('' === $domain->label(0)) {
            throw SyntaxError::dueToInvalidSuffix($domain);
        }

        return $domain;
    }

    public function isKnown(): bool
    {
        return self::UNKNOWN !== $this->section;
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

    public function toAscii(): self
    {
        return new self($this->domain->toAscii(), $this->section);
    }

    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->section);
    }

    /**
     * @throws CannotProcessHost
     */
    public function normalize(DomainName $domain): self
    {
        $newDomain = $domain->clear()->append($this->toUnicode());
        if ($domain->isAscii()) {
            $newDomain = $newDomain->toAscii();
        }

        return new self($newDomain, $this->section);
    }
}
