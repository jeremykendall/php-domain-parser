<?php

declare(strict_types=1);

namespace Pdp;

use Stringable;
use function count;

final class ResolvedDomain implements ResolvedDomainName
{
    private DomainName $secondLevelDomain;
    private DomainName $registrableDomain;
    private DomainName $subDomain;

    private function __construct(
        private DomainName $domain,
        private EffectiveTopLevelDomain $suffix
    ) {
        $this->computeComponents();
    }

    /**
     * Make sure the Value Object is always in a valid state.
     *
     * @throws UnableToResolveDomain If the suffix can not be attached to the domain
     */
    private function computeComponents(): void
    {
        $length = count($this->suffix);
        if (0 === $length) {
            $nullDomain = $this->domain->clear();
            $this->registrableDomain = $nullDomain;
            $this->secondLevelDomain = $nullDomain;
            $this->subDomain = $nullDomain;
            return;
        }

        if ($this->domain->value() === $this->suffix->value()) {
            throw UnableToResolveDomain::dueToIdenticalValue($this->domain);
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        $this->registrableDomain = $this->domain->slice(0, $length + 1);
        $this->secondLevelDomain = $this->domain->slice($length, 1);
        $this->subDomain = $this->domain->slice($length + 1);
    }

    public static function fromICANN(DomainNameProvider|DomainName|Host|Stringable|string|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromICANN($domain->slice(0, $suffixLength)));
    }

    public static function fromPrivate(DomainNameProvider|DomainName|Host|Stringable|string|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromPrivate($domain->slice(0, $suffixLength)));
    }

    public static function fromIANA(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromIANA($domain->label(0)));
    }

    public static function fromUnknown(DomainNameProvider|DomainName|Host|Stringable|string|null $domain, int $suffixLength = 0): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromUnknown($domain->slice(0, $suffixLength)));
    }

    /**
     * @param array{domain:DomainName, suffix:EffectiveTopLevelDomain} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['suffix']);
    }

    private static function setDomainName(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            return $domain->domain();
        }

        if ($domain instanceof DomainName) {
            return $domain;
        }

        return Domain::fromIDNA2008($domain);
    }

    public function count(): int
    {
        return count($this->domain);
    }

    public function domain(): DomainName
    {
        return $this->domain;
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

    public function registrableDomain(): DomainName
    {
        return $this->registrableDomain;
    }

    public function secondLevelDomain(): DomainName
    {
        return $this->secondLevelDomain;
    }

    public function subDomain(): DomainName
    {
        return $this->subDomain;
    }

    public function suffix(): EffectiveTopLevelDomain
    {
        return $this->suffix;
    }

    public function toAscii(): static
    {
        return new self($this->domain->toAscii(), $this->suffix->toAscii());
    }

    public function toUnicode(): static
    {
        return new self($this->domain->toUnicode(), $this->suffix->toUnicode());
    }

    public function withSuffix(DomainNameProvider|DomainName|Host|Stringable|string|null $suffix): self
    {
        if (!$suffix instanceof EffectiveTopLevelDomain) {
            $suffix = Suffix::fromUnknown($suffix);
        }

        return new self(
            $this->domain->slice(count($this->suffix))->append($suffix),
            $suffix->normalize($this->domain)
        );
    }

    public function withSubDomain(DomainNameProvider|DomainName|Host|Stringable|string|null $subDomain): self
    {
        if (null === $this->suffix->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        $subDomain = $this->domain->clear()->append(self::setDomainName($subDomain));
        if ($this->subDomain->value() === $subDomain->value()) {
            return $this;
        }

        return new self($this->registrableDomain->prepend($subDomain), $this->suffix);
    }

    public function withSecondLevelDomain(DomainNameProvider|DomainName|Host|Stringable|string|null $label): self
    {
        if (null === $this->suffix->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        $label = self::setDomainName($label);
        if (1 !== count($label)) {
            throw UnableToResolveDomain::dueToInvalidSecondLevelDomain($label);
        }

        $newRegistrableDomain = $this->registrableDomain->withoutLabel(-1)->prepend($label);
        if ($newRegistrableDomain->value() === $this->registrableDomain->value()) {
            return $this;
        }

        return new self($newRegistrableDomain->prepend($this->subDomain), $this->suffix);
    }
}
