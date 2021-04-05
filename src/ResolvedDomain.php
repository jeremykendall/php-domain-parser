<?php

declare(strict_types=1);

namespace Pdp;

use function count;

final class ResolvedDomain implements ResolvedDomainName
{
    private DomainName $domain;

    private EffectiveTopLevelDomain $suffix;

    private DomainName $secondLevelDomain;

    private DomainName $registrableDomain;

    private DomainName $subDomain;

    private function __construct(DomainName $domain, EffectiveTopLevelDomain $suffix)
    {
        $this->domain = $domain;
        $this->suffix = $suffix;

        $this->validateState();
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromICANN($domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromICANN($domain->slice(0, $suffixLength)));
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromPrivate($domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromPrivate($domain->slice(0, $suffixLength)));
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromIANA($domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromIANA($domain->label(0)));
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromUnknown($domain, int $suffixLength = 0): self
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

    /**
     * @param mixed $domain The domain to be resolved
     */
    private static function setDomainName($domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            return $domain->domain();
        }

        if ($domain instanceof DomainName) {
            return $domain;
        }

        return Domain::fromIDNA2008($domain);
    }

    /**
     * Make sure the Value Object is always in a valid state.
     *
     * @throws UnableToResolveDomain If the suffix can not be attached to the domain
     */
    private function validateState(): void
    {
        $suffixValue = $this->suffix->value();
        if (null === $suffixValue) {
            $nullDomain = $this->domain->clear();
            $this->registrableDomain = $nullDomain;
            $this->secondLevelDomain = $nullDomain;
            $this->subDomain = $nullDomain;
            return;
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ($this->domain->value() === $suffixValue) {
            throw UnableToResolveDomain::dueToIdenticalValue($this->domain);
        }

        $length = count($this->suffix);
        $this->registrableDomain = $this->domain->slice(0, $length + 1);
        $this->secondLevelDomain = $this->domain->slice($length, 1);
        $this->subDomain = $this->domain->slice($length + 1);
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

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toAscii(): self
    {
        return new self($this->domain->toAscii(), $this->suffix->toAscii());
    }

    /**
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->suffix->toUnicode());
    }

    /**
     * @param mixed $suffix the suffix
     */
    public function withSuffix($suffix): self
    {
        if (!$suffix instanceof EffectiveTopLevelDomain) {
            $suffix = Suffix::fromUnknown($suffix);
        }

        return new self(
            $this->domain->slice(count($this->suffix))->append($suffix),
            $suffix->normalize($this->domain)
        );
    }

    /**
     * @param mixed $subDomain the sub domain
     */
    public function withSubDomain($subDomain): self
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

    /**
     * @param mixed $label the second level domain
     */
    public function withSecondLevelDomain($label): self
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
