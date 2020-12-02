<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

final class UnableToResolveDomain extends InvalidArgumentException implements CannotProcessHost
{
    private DomainName $domain;

    public static function dueToIdenticalValue(DomainName $domain): self
    {
        $exception = new self('The public suffix and the domain name are is identical `'.$domain->toString().'`.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToMismatchedSuffix(DomainName $domain, EffectiveTopLevelDomain $effectiveTLD): self
    {
        $exception = new self('The public suffix `'.$effectiveTLD->value().'` can not be assign to the domain name `'.$domain->toString().'`');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToMissingSuffix(DomainName $domain, string $type): self
    {
        $exception = new self('The domain "'.$domain->value().'" does not contain a "'.$type.'" TLD.');
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

    public function getDomain(): DomainName
    {
        return $this->domain;
    }
}
