<?php

declare(strict_types=1);

namespace Pdp;

use JsonSerializable;

interface PublicSuffixList extends DomainResolver, JsonSerializable
{
    /**
     * Returns an array representation of the Public Suffix List Rules JSON serializable.
     */
    public function jsonSerialize(): array;

    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @throws InvalidDomainName     if the domain is invalid
     * @throws UnableToResolveDomain if the effective TLD can not be resolved
     */
    public function getCookieDomain(Host $host): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @throws InvalidDomainName     if the domain is invalid
     * @throws UnableToResolveDomain if the domain does not contain a ICANN Effective TLD
     */
    public function getICANNDomain(Host $host): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @throws InvalidDomainName     if the domain is invalid
     * @throws UnableToResolveDomain if the domain does not contain a private Effective TLD
     */
    public function getPrivateDomain(Host $host): ResolvedDomainName;
}
