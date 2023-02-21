<?php

declare(strict_types=1);

namespace Pdp;

interface ResolvedDomainName extends Host, DomainNameProvider
{
    /**
     * Returns the domain effective tld component.
     */
    public function suffix(): EffectiveTopLevelDomain;

    /**
     * Returns the second level domain component.
     */
    public function secondLevelDomain(): DomainName;

    /**
     * Returns the registrable domain component.
     */
    public function registrableDomain(): DomainName;

    /**
     * Returns the subdomain component.
     */
    public function subDomain(): DomainName;

    /**
     * Returns an instance with the specified subdomain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new subdomain
     *
     * @throws CannotProcessHost If the Sub domain can not be added to the current Domain
     */
    public function withSubDomain(DomainName $subDomain): self;

    /**
     * Returns an instance with the specified second level domain label added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the second level domain label
     *
     * @throws CannotProcessHost If the second level domain label can not be added
     */
    public function withSecondLevelDomain(DomainName $label): self;

    /**
     * Returns an instance with the specified public suffix added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new public suffix
     *
     * If the domain already has a public suffix it will be replaced by the new value
     * otherwise the public suffix content is added to or remove from the current domain.
     */
    public function withSuffix(EffectiveTopLevelDomain $suffix): self;
}
