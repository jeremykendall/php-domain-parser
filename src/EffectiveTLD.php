<?php

declare(strict_types=1);

namespace Pdp;

interface EffectiveTLD extends Host, DomainNameProvider
{
    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List.
     */
    public function isKnown(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in the IANA Root Zone Database.
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
}
