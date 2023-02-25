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

    /**
     * @throws CannotProcessHost
     */
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

    /**
     * @throws CannotProcessHost
     */
    public static function fromICANN(DomainNameProvider|Host|Stringable|string|int|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromICANN($domain->slice(0, $suffixLength)));
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromPrivate(DomainNameProvider|Host|Stringable|string|int|null $domain, int $suffixLength): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromPrivate($domain->slice(0, $suffixLength)));
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromIANA(DomainNameProvider|Host|Stringable|string|int|null $domain): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromIANA($domain->label(0)));
    }

    /**
     * @throws CannotProcessHost
     */
    public static function fromUnknown(DomainNameProvider|Host|Stringable|string|int|null $domain, int $suffixLength = 0): self
    {
        $domain = self::setDomainName($domain);

        return new self($domain, Suffix::fromUnknown($domain->slice(0, $suffixLength)));
    }

    /**
     * @param array{domain:DomainName, suffix:EffectiveTopLevelDomain} $properties
     *
     * @throws CannotProcessHost
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['suffix']);
    }

    /**
     * @throws CannotProcessHost
     */
    private static function setDomainName(DomainNameProvider|Host|Stringable|string|int|null $domain): DomainName
    {
        return match (true) {
            $domain instanceof DomainNameProvider => $domain->domain(),
            $domain instanceof DomainName => $domain,
            default => RegisteredName::fromIDNA2008($domain),
        };
    }

    /**
     * Make sure the Value Object is always in a valid state.
     *
     * @throws UnableToResolveDomain|CannotProcessHost If the suffix can not be attached to the domain
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
            'subDomain' => RegisteredName::fromIDNA2008($this->domain->value())->slice($length + 1),
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
        return new self($this->domain->toAscii(), $this->suffix->toAscii());
    }

    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->suffix->toUnicode());
    }

    /**
     * @throws CannotProcessHost
     */
    public function withSuffix(DomainNameProvider|Host|Stringable|string|int|null $suffix): self
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
     * @throws CannotProcessHost|UnableToResolveDomain
     */
    public function withSubDomain(DomainNameProvider|Host|Stringable|string|int|null $subDomain): self
    {
        if (null === $this->suffix->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        $subDomain = RegisteredName::fromIDNA2008($subDomain);
        if ($this->subDomain->value() === $subDomain->value()) {
            return $this;
        }

        return new self($this->registrableDomain->prepend($subDomain), $this->suffix);
    }

    /**
     * @throws CannotProcessHost|UnableToResolveDomain
     */
    public function withSecondLevelDomain(DomainNameProvider|Host|Stringable|string|int|null $label): self
    {
        if (null === $this->suffix->value()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this->domain);
        }

        $label = RegisteredName::fromIDNA2008($label);
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
