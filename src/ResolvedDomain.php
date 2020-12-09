<?php

declare(strict_types=1);

namespace Pdp;

use function count;
use function substr;

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
        $this->suffix = $this->setSuffix($suffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->secondLevelDomain = $this->setSecondLevelDomain();
        $this->subDomain = $this->setSubDomain();
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

        return new self($domain, Suffix::fromIANA($domain->slice(0, 1)));
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromUnknown($domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromUnknown($domain->slice(0, 1)));
    }

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromNull($domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromUnknown($domain->clear()));
    }

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
     * Sets the public suffix domain part.
     *
     * @throws UnableToResolveDomain If the public suffic can not be attached to the domain
     */
    private function setSuffix(EffectiveTopLevelDomain $suffix): EffectiveTopLevelDomain
    {
        if (null === $suffix->value()) {
            return $suffix;
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ('.' === substr($this->domain->toString(), -1, 1)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ($this->domain->value() === $suffix->value()) {
            throw UnableToResolveDomain::dueToIdenticalValue($this->domain);
        }

        if ($this->domain->slice(0, count($suffix))->value() !== $suffix->value()) {
            throw UnableToResolveDomain::dueToMismatchedSuffix($this->domain, $suffix);
        }

        return $suffix;
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain(): DomainName
    {
        if (null === $this->suffix->value()) {
            return $this->domain->clear();
        }

        return $this->domain->slice(0, count($this->suffix) + 1);
    }

    private function setSecondLevelDomain(): DomainName
    {
        if (null === $this->suffix->value()) {
            return $this->domain->clear();
        }

        return $this->domain->slice(count($this->suffix), 1);
    }

    /**
     * Computes the sub domain part.
     */
    private function setSubDomain(): DomainName
    {
        if (null === $this->suffix->value()) {
            return $this->domain->clear();
        }

        return $this->domain->slice(count($this->suffix) + 1);
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

    public function toAscii(): self
    {
        return new self($this->domain->toAscii(), $this->suffix->toAscii());
    }

    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->suffix->toUnicode());
    }

    /**
     * @param mixed $suffix a public suffix
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
     * {@inheritDoc}
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

        $subDomain = $subDomain->toAscii();
        if (!$this->domain->isAscii()) {
            $subDomain = $subDomain->toUnicode();
        }

        return new self($this->registrableDomain->prepend($subDomain), $this->suffix);
    }

    /**
     * {@inheritDoc}
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
