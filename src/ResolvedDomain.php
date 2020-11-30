<?php

declare(strict_types=1);

namespace Pdp;

use function array_reverse;
use function array_slice;
use function count;
use function explode;
use function implode;
use function strlen;
use function substr;

final class ResolvedDomain implements ResolvedDomainName
{
    private DomainName $domain;

    private EffectiveTLD $suffix;

    private DomainName $registrableDomain;

    private DomainName $subDomain;

    public function __construct(Host $domain, EffectiveTLD $suffix = null)
    {
        $this->domain = $this->setDomainName($domain);
        $this->suffix = $this->setSuffix($suffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['suffix']);
    }

    private function setDomainName(Host $domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            return $domain->domain();
        }

        if ($domain instanceof DomainName) {
            return $domain;
        }

        return Domain::fromIDNA2008($domain->value());
    }

    /**
     * Sets the public suffix domain part.
     *
     * @throws UnableToResolveDomain If the public suffic can not be attached to the domain
     */
    private function setSuffix(EffectiveTLD $suffix = null): EffectiveTLD
    {
        if (null === $suffix || null === $suffix->value()) {
            $domain = $this->domain->isIdna2008() ? Domain::fromIDNA2008(null) : Domain::fromIDNA2003(null);

            return Suffix::fromUnknown($domain);
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ('.' === substr($this->domain->toString(), -1, 1)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        $suffix = $this->normalize($suffix);
        if ($this->domain->value() === $suffix->value()) {
            throw UnableToResolveDomain::dueToIdenticalValue($this->domain);
        }

        $psContent = $suffix->toString();
        if ('.'.$psContent !== substr($this->domain->toString(), - strlen($psContent) - 1)) {
            throw UnableToResolveDomain::dueToMismatchedSuffix($this->domain, $suffix);
        }

        return $suffix;
    }

    /**
     * Normalize the domain name encoding content.
     */
    private function normalize(EffectiveTLD $subject): EffectiveTLD
    {
        if ($subject->domain()->isIdna2008() === $this->domain->isIdna2008()) {
            return $subject->domain()->isAscii() === $this->domain->isAscii() ? $subject : $subject->toUnicode();
        }

        $newDomain = Domain::fromIDNA2003($subject->toUnicode()->value());
        if ($this->domain->isAscii()) {
            $newDomain = $newDomain->toAscii();
        }

        if ($subject->isPrivate()) {
            return Suffix::fromPrivate($newDomain);
        }

        if ($subject->isICANN()) {
            return  Suffix::fromICANN($newDomain);
        }

        return Suffix::fromUnknown($newDomain);
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain(): DomainName
    {
        if (null === $this->suffix->value()) {
            return $this->domain->isIdna2008() ? Domain::fromIDNA2008(null) : Domain::fromIDNA2003(null);
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            count($this->domain) - count($this->suffix) - 1
        ));

        $registrableDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($domain) : Domain::fromIDNA2003($domain);

        return $this->domain->isAscii() ? $registrableDomain->toAscii() : $registrableDomain->toUnicode();
    }

    /**
     * Computes the sub domain part.
     */
    private function setSubDomain(): DomainName
    {
        if (null === $this->registrableDomain->value()) {
            return $this->domain->isIdna2008() ? Domain::fromIDNA2008(null) : Domain::fromIDNA2003(null);
        }

        $nbLabels = count($this->domain);
        $nbRegistrableLabels = count($this->suffix) + 1;
        if ($nbLabels === $nbRegistrableLabels) {
            return $this->domain->isIdna2008() ? Domain::fromIDNA2008(null) : Domain::fromIDNA2003(null);
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            0,
            $nbLabels - $nbRegistrableLabels
        ));

        $subDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($domain) : Domain::fromIDNA2003($domain);

        return $this->domain->isAscii() ? $subDomain->toAscii() : $subDomain->toUnicode();
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

    public function registrableDomain(): DomainName
    {
        return $this->registrableDomain;
    }

    public function secondLevelDomain(): ?string
    {
        return $this->registrableDomain->label(-1);
    }

    public function subDomain(): DomainName
    {
        return $this->subDomain;
    }

    public function suffix(): EffectiveTLD
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
        if (!$suffix instanceof EffectiveTLD) {
            $suffix = Suffix::fromUnknown($suffix);
        }

        $suffix = $this->normalize($suffix);
        if ($this->suffix == $suffix) {
            return $this;
        }

        $host = implode('.', array_reverse(array_slice($this->domain->labels(), count($this->suffix))));

        if (null === $suffix->value()) {
            $domain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($host) : Domain::fromIDNA2003($host);

            return new self($domain, null);
        }

        $host .= '.'.$suffix->value();
        $domain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($host) : Domain::fromIDNA2003($host);

        return new self($domain, $suffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withSubDomain($subDomain): self
    {
        if (null === $this->registrableDomain->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        if ($subDomain instanceof DomainNameProvider) {
            $subDomain = $subDomain->domain();
        }

        if (!$subDomain instanceof DomainName) {
            $subDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($subDomain) : Domain::fromIDNA2003($subDomain);
        }

        $subDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($subDomain) : Domain::fromIDNA2003($subDomain);
        if ($this->subDomain == $subDomain) {
            return $this;
        }

        /** @var DomainName $subDomain */
        $subDomain = $subDomain->toAscii();
        if (!$this->domain->isAscii()) {
            /** @var DomainName $subDomain */
            $subDomain = $subDomain->toUnicode();
        }

        $newDomainValue = $subDomain->toString().'.'.$this->registrableDomain->toString();
        $newDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($newDomainValue) : Domain::fromIDNA2003($newDomainValue);

        return new self($newDomain, $this->suffix);
    }

    public function withSecondLevelDomain($label): self
    {
        if (null === $this->registrableDomain->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        $newRegistrableDomain = $this->registrableDomain->withLabel(-1, $label);
        if ($newRegistrableDomain == $this->registrableDomain) {
            return $this;
        }

        if (null === $this->subDomain->value()) {
            return new self($newRegistrableDomain, $this->suffix);
        }

        $newDomainValue = $this->subDomain->value().'.'.$newRegistrableDomain->value();
        $newDomain = $this->domain->isIdna2008() ? Domain::fromIDNA2008($newDomainValue) : Domain::fromIDNA2003($newDomainValue);

        return new self($newDomain, $this->suffix);
    }
}
