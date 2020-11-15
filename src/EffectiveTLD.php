<?php

declare(strict_types=1);

namespace Pdp;

interface EffectiveTLD extends Host, ExternalDomainName, IDNConversion
{
    public const ICANN_DOMAINS = 'ICANN_DOMAINS';

    public const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List.
     */
    public function isKnown(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List ICANN Section.
     */
    public function isICANN(): bool;

    /**
     * Tells whether the effective TLD has a matching rule in a Public Suffix List Private Section.
     */
    public function isPrivate(): bool;
}
