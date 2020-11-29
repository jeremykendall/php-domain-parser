<?php

declare(strict_types=1);

namespace Pdp;

interface ResolvedDomainName extends Host, ExternalDomainName
{
    /**
     * Returns the domain effective tld component.
     */
    public function suffix(): EffectiveTLD;

    /**
     * Returns the second level domain component.
     */
    public function secondLevelDomain(): ?string;

    /**
     * Returns the registrable domain component.
     */
    public function registrableDomain(): DomainName;

    /**
     * Returns the sub domain component.
     */
    public function subDomain(): DomainName;

    /**
     * Returns an instance with the specified sub domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new sub domain
     *
     * @throws CannotProcessHost If the Sub domain can not be added to the current Domain
     */
    public function withSubDomain(Host $subDomain): self;

    /**
     * Returns an instance with the specified second level domain label added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the second level domain label
     *
     * @param  ?string           $label
     * @throws CannotProcessHost If the second level domain label can not be added
     */
    public function withSecondLevelDomain(?string $label): self;

    /**
     * Returns an instance with the specified public suffix added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new public suffix
     *
     * If the domain already has a public suffix it will be replaced by the new value
     * otherwise the public suffix content is added to or remove from the current domain.
     */
    public function withSuffix(EffectiveTLD $publicSuffix): self;
}
