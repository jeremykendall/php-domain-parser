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

    private DomainName $secondLevelDomain;

    private DomainName $registrableDomain;

    private DomainName $subDomain;

    /**
     * @param mixed $domain the domain to be resolved
     */
    public static function fromHost($domain, EffectiveTLD $suffix = null): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, $suffix ?? Suffix::fromUnknown($domain->clear()));
    }

    private function __construct(DomainName $domain, EffectiveTLD $suffix)
    {
        $this->domain = $domain;
        $this->suffix = $this->setSuffix($suffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->secondLevelDomain = $this->setSecondLevelDomain();
        $this->subDomain = $this->setSubDomain();
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
    private function setSuffix(EffectiveTLD $suffix): EffectiveTLD
    {
        if (null === $suffix->value()) {
            return Suffix::fromUnknown($this->domain->clear());
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
        $newDomain = $this->domain->clear()->append($subject->toUnicode()->value());
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
            return $this->domain->clear();
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            count($this->domain) - count($this->suffix) - 1
        ));

        $registrableDomain = $this->domain->clear()->append($domain);

        return $this->domain->isAscii() ? $registrableDomain->toAscii() : $registrableDomain->toUnicode();
    }

    private function setSecondLevelDomain(): DomainName
    {
        return $this->registrableDomain->clear()->append($this->registrableDomain->label(-1));
    }

    /**
     * Computes the sub domain part.
     */
    private function setSubDomain(): DomainName
    {
        if (null === $this->registrableDomain->value()) {
            return $this->domain->clear();
        }

        $nbLabels = count($this->domain);
        $nbRegistrableLabels = count($this->suffix) + 1;
        if ($nbLabels === $nbRegistrableLabels) {
            return $this->domain->clear();
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            0,
            $nbLabels - $nbRegistrableLabels
        ));

        $subDomain = $this->domain->clear()->append($domain);

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

    public function secondLevelDomain(): DomainName
    {
        return $this->secondLevelDomain;
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
            return new self($this->domain->clear()->append($host), Suffix::fromUnknown($this->domain->clear()));
        }

        $host .= '.'.$suffix->value();

        return new self($this->domain->clear()->append($host), $suffix);
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
            $subDomain = $this->domain->clear()->append($subDomain);
        }

        $subDomain = $this->domain->clear()->append($subDomain);
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

        return new self($this->domain->clear()->append($newDomainValue), $this->suffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withSecondLevelDomain($label): self
    {
        if (null === $this->registrableDomain->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        if ($label instanceof DomainNameProvider) {
            $label = $label->domain();
        }

        if (!$label instanceof DomainName) {
            $label = Domain::fromIDNA2008($label);
        }

        $newRegistrableDomain = $this->registrableDomain->withoutLabel(-1)->prepend($label->value());
        if ($newRegistrableDomain == $this->registrableDomain) {
            return $this;
        }

        if (null === $this->subDomain->value()) {
            return new self($newRegistrableDomain, $this->suffix);
        }

        $newDomainValue = $this->subDomain->value().'.'.$newRegistrableDomain->value();

        return new self($this->domain->clear()->append($newDomainValue), $this->suffix);
    }
}
