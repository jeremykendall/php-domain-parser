<?php

declare(strict_types=1);

namespace Pdp;

interface PublicSuffixList extends DomainNameResolver
{
    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @throws SyntaxError           if the domain is invalid
     * @throws UnableToResolveDomain if the effective TLD can not be resolved
     */
    public function getCookieDomain(Host $host): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @throws SyntaxError           if the domain is invalid
     * @throws UnableToResolveDomain if the domain does not contain a ICANN Effective TLD
     */
    public function getICANNDomain(Host $host): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @throws SyntaxError           if the domain is invalid
     * @throws UnableToResolveDomain if the domain does not contain a private Effective TLD
     */
    public function getPrivateDomain(Host $host): ResolvedDomainName;
}
