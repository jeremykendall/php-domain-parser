<?php

declare(strict_types=1);

namespace Pdp;

interface DomainResolver
{
    /**
     * Returns the effective TLD info for a given domain against an external data source.
     *
     * If the effective TLD can not be resolved it returns a ResolvedDomainName with a null public suffix
     * If the host is not a valid domain it returns a ResolvedDomainName with a null Domain name
     */
    public function resolve(Host $host): ResolvedDomainName;
}
