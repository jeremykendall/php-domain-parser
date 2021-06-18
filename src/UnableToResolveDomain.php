<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

final class UnableToResolveDomain extends InvalidArgumentException implements CannotProcessHost
{
    private ?DomainName $domain;

    private function __construct(string $message, ?DomainName $domain = null)
    {
        parent::__construct($message);
        $this->domain = $domain;
    }

    public static function dueToInvalidSecondLevelDomain(DomainName $domain): self
    {
        return new self('The submitted Second Level Domain is invalid `'.($domain->value() ?? 'NULL').'`; it should contains only one label.', $domain);
    }

    public static function dueToIdenticalValue(DomainName $domain): self
    {
        return new self('The public suffix and the domain name are identical `'.$domain->toString().'`.', $domain);
    }

    public static function dueToMissingSuffix(DomainName $domain, string $type): self
    {
        return new self('The domain "'.($domain->value() ?? 'NULL').'" does not contain a "'.$type.'" TLD.', $domain);
    }

    public static function dueToUnresolvableDomain(DomainName $domain): self
    {
        return new self('The domain "'.($domain->value() ?? 'NULL').'" can not contain a public suffix.', $domain);
    }

    public static function dueToMissingRegistrableDomain(DomainName $domain): self
    {
        return new self('A subdomain can not be added to a domain "'.($domain->value() ?? 'NULL').'" without a registrable domain part.', $domain);
    }

    public function domain(): ?DomainName
    {
        return $this->domain;
    }
}
