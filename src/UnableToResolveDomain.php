<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

final class UnableToResolveDomain extends InvalidArgumentException implements CannotProcessHost
{
    private ?DomainName $domain = null;

    public static function dueToMissingPublicSuffix(DomainName $domain, string $type): self
    {
        $domainType = (EffectiveTLD::PRIVATE_DOMAINS === $type) ? 'private' : 'ICANN';

        $exception = new self('The domain "'.$domain->value().'" does not contain a "'.$domainType.'" TLD.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToUnresolvableDomain(DomainName $domain): self
    {
        $exception = new self('The domain "'.$domain->value().'" can not contain a public suffix.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToMissingRegistrableDomain(DomainName $domain): self
    {
        $exception = new self('A subdomain can not be added to a domain "'.$domain->value().'" without a registrable domain part.');
        $exception->domain = $domain;

        return $exception;
    }

    public function fetchDomain(): ?DomainName
    {
        return $this->domain;
    }
}
