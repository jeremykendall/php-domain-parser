<?php

declare(strict_types=1);

namespace Pdp;

interface EffectiveTopLevelDomain extends Host, DomainNameProvider
{
    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List.
     */
    public function isKnown(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in the IANA Top Level Domain List.
     */
    public function isIANA(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in the Public Suffix List.
     */
    public function isPublicSuffix(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List ICANN Section.
     */
    public function isICANN(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List Private Section.
     */
    public function isPrivate(): bool;

    /**
     * Returns an instance with the public suffix normalize to the submitted domain encoding algorithm.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the same domain information normalize against the submitted domainName
     */
    public function normalize(DomainName $domain): self;
}
