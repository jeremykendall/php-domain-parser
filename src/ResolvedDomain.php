<?php

declare(strict_types=1);

namespace Pdp;

use Stringable;
use function count;

final class ResolvedDomain implements ResolvedDomainName
{
    private readonly DomainName $secondLevelDomain;
    private readonly DomainName $registrableDomain;
    private readonly DomainName $subDomain;

    private function __construct(
        private readonly DomainName $domain,
        private readonly EffectiveTopLevelDomain $suffix
    ) {
        [
            'registrableDomain' => $this->registrableDomain,
            'secondLevelDomain' => $this->secondLevelDomain,
            'subDomain' => $this->subDomain,
        ] = $this->parse();
    }

    public static function fromICANN(int|DomainNameProvider|Host|string|Stringable|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromICANN($domain->slice(0, $suffixLength)));
    }

    public static function fromPrivate(int|DomainNameProvider|Host|string|Stringable|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromPrivate($domain->slice(0, $suffixLength)));
    }

    public static function fromIANA(int|DomainNameProvider|Host|string|Stringable|null $domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromIANA($domain->label(0)));
    }

    public static function fromUnknown(int|DomainNameProvider|Host|string|Stringable|null $domain, int $suffixLength = 0): self
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

    private static function setDomainName(int|DomainNameProvider|Host|string|Stringable|null $domain): DomainName
    {
        return match (true) {
            $domain instanceof DomainNameProvider => $domain->domain(),
            $domain instanceof DomainName => $domain,
            default => Domain::fromIDNA2008($domain),
        };
    }

    /**
     * Make sure the Value Object is always in a valid state.
     *
     * @throws UnableToResolveDomain If the suffix can not be attached to the domain
     *
     * @return array{registrableDomain: DomainName, secondLevelDomain: DomainName, subDomain: DomainName}
     */
    private function parse(): array
    {
        $suffixValue = $this->suffix->value();
        if (null === $suffixValue) {
            $nullDomain = $this->domain->clear();

            return [
                'registrableDomain' => $nullDomain,
                'secondLevelDomain' => $nullDomain,
                'subDomain' => $nullDomain,
            ];
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ($this->domain->value() === $suffixValue) {
            throw UnableToResolveDomain::dueToIdenticalValue($this->domain);
        }

        $length = count($this->suffix);

        return [
            'registrableDomain' => $this->domain->slice(0, $length + 1),
            'secondLevelDomain' => $this->domain->slice($length, 1),
            'subDomain' => $this->domain->slice($length + 1),
        ];
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

    public function withSuffix(int|DomainNameProvider|Host|string|Stringable|null $suffix): self
    {
        if (!$suffix instanceof EffectiveTopLevelDomain) {
            $suffix = Suffix::fromUnknown($suffix);
        }

        return new self(
            $this->domain->slice(count($this->suffix))->append($suffix),
            $suffix->normalize($this->domain)
        );
    }

    public function withSubDomain(int|DomainNameProvider|Host|string|Stringable|null $subDomain): self
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
     * @param int|DomainNameProvider|Host|string|Stringable|null $label the second level domain
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
