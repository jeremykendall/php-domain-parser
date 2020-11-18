<?php

declare(strict_types=1);

namespace Pdp;

use function array_reverse;
use function array_slice;
use function count;
use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function strlen;
use function substr;

final class ResolvedDomain implements ResolvedDomainName
{
    private const REGEXP_IDN_PATTERN = '/[^\x20-\x7f]/';

    private DomainName $domain;

    private EffectiveTLD $publicSuffix;

    private DomainName $registrableDomain;

    private DomainName $subDomain;

    public function __construct(Host $domain, ?EffectiveTLD $publicSuffix = null)
    {
        $this->domain = $this->setDomainName($domain);
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['publicSuffix']);
    }

    private function setDomainName(Host $domain): DomainName
    {
        if ($domain instanceof ExternalDomainName) {
            return $domain->getDomain();
        }

        if (!$domain instanceof DomainName) {
            return new Domain($domain);
        }

        return $domain;
    }

    /**
     * Sets the public suffix domain part.
     *
     * @throws UnableToResolveDomain If the public suffic can not be attached to the domain
     */
    private function setPublicSuffix(EffectiveTLD $publicSuffix = null): EffectiveTLD
    {
        if (null === $publicSuffix || null === $publicSuffix->value()) {
            return PublicSuffix::fromNull();
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ('.' === substr($this->domain->toString(), -1, 1)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->domain->value() === $publicSuffix->value()) {
            throw new UnableToResolveDomain(sprintf('The public suffix and the domain name are is identical `%s`.', $this->domain->toString()));
        }

        $psContent = $publicSuffix->toString();
        if ('.'.$psContent !== substr($this->domain->toString(), - strlen($psContent) - 1)) {
            throw new UnableToResolveDomain(sprintf('The public suffix `%s` can not be assign to the domain name `%s`', $psContent, $this->domain->toString()));
        }

        return $publicSuffix;
    }

    /**
     * Normalize the domain name encoding content.
     */
    private function normalize(EffectiveTLD $subject): EffectiveTLD
    {
        $subject = $subject->withValue(
            $subject->value(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        );

        if (1 !== preg_match(self::REGEXP_IDN_PATTERN, $this->domain->toString())) {
            return $subject->toAscii();
        }

        return $subject->toUnicode();
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain(): DomainName
    {
        if (null === $this->publicSuffix->value()) {
            return Domain::fromNull($this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption());
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            count($this->domain) - count($this->publicSuffix) - 1
        ));

        return new Domain($domain, $this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption());
    }

    /**
     * Computes the sub domain part.
     */
    private function setSubDomain(): DomainName
    {
        $asciiIDNAOptions = $this->domain->getAsciiIDNAOption();
        $unicodeIDNAOptions = $this->domain->getUnicodeIDNAOption();

        if (null === $this->registrableDomain->value()) {
            return Domain::fromNull($asciiIDNAOptions, $unicodeIDNAOptions);
        }

        $nbLabels = count($this->domain);
        $nbRegistrableLabels = count($this->publicSuffix) + 1;
        if ($nbLabels === $nbRegistrableLabels) {
            return Domain::fromNull($asciiIDNAOptions, $unicodeIDNAOptions);
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->toString()),
            0,
            $nbLabels - $nbRegistrableLabels
        ));

        return new Domain($domain, $asciiIDNAOptions, $unicodeIDNAOptions);
    }

    public function count(): int
    {
        return count($this->domain);
    }

    public function getDomain(): DomainName
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

    public function getRegistrableDomain(): ResolvedDomain
    {
        return new self($this->registrableDomain, $this->publicSuffix);
    }

    public function getSecondLevelDomain(): ?string
    {
        return $this->registrableDomain->label(-1);
    }

    public function getSubDomain(): DomainName
    {
        return $this->subDomain;
    }

    public function getPublicSuffix(): EffectiveTLD
    {
        return $this->publicSuffix;
    }

    public function toAscii(): self
    {
        return new self($this->domain->toAscii(), $this->publicSuffix->toAscii());
    }

    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->publicSuffix->toUnicode());
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public function withPublicSuffix($publicSuffix): self
    {
        if (!$publicSuffix instanceof EffectiveTLD) {
            if ($publicSuffix instanceof ExternalDomainName) {
                $publicSuffix = PublicSuffix::fromUnknown($publicSuffix->getDomain());
            } elseif ($publicSuffix instanceof DomainName) {
                $publicSuffix = PublicSuffix::fromUnknown($publicSuffix);
            } else {
                $publicSuffix = PublicSuffix::fromUnknown(new Domain($publicSuffix));
            }
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $host = implode('.', array_reverse(array_slice($this->domain->labels(), count($this->publicSuffix))));
        if (null === $publicSuffix->value()) {
            return new self(
                new Domain($host, $this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption()),
                null
            );
        }

        /** @var DomainName $domain */
        $domain = new Domain(
            $host.'.'.$publicSuffix->value(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        );

        return new self($domain, $publicSuffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withSubDomain($subDomain): self
    {
        if (null === $this->registrableDomain->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this);
        }

        if (!$subDomain instanceof DomainName) {
            $subDomain = new Domain(
                $subDomain,
                $this->domain->getAsciiIDNAOption(),
                $this->domain->getUnicodeIDNAOption()
            );
        }

        $subDomain = $subDomain->withValue(
            $subDomain->value(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        );

        if ($this->subDomain == $subDomain) {
            return $this;
        }

        /** @var DomainName $subDomain */
        $subDomain = $subDomain->toAscii();
        if (1 === preg_match(self::REGEXP_IDN_PATTERN, $this->domain->toString())) {
            /** @var DomainName $subDomain */
            $subDomain = $subDomain->toUnicode();
        }

        return new self(new Domain(
            $subDomain->toString().'.'.$this->registrableDomain->toString(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        ), $this->publicSuffix);
    }

    public function withSecondLevelDomain($label): self
    {
        if (null === $this->registrableDomain->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this);
        }

        $newRegistrableDomain = $this->registrableDomain->withLabel(-1, $label);
        if ($newRegistrableDomain == $this->registrableDomain) {
            return $this;
        }

        if (null === $this->subDomain->value()) {
            return new self($newRegistrableDomain, $this->publicSuffix);
        }

        return new self(new Domain(
            $this->subDomain->value().'.'.$newRegistrableDomain->value(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        ), $this->publicSuffix);
    }
}
