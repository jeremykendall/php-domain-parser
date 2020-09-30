<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

interface ResolvedDomainName extends Host, ExternalDomainName
{
    public function getPublicSuffix(): EffectiveTLD;

    public function getSecondLevelDomain(): ?string;

    public function getRegistrableDomain(): self;

    public function getSubDomain(): DomainName;

    /**
     * Returns an instance with the specified sub domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new sub domain
     *
     * @throws ExceptionInterface If the Sub domain can not be added to the current Domain
     */
    public function withSubDomain(Host $subDomain): self;

    /**
     * Returns an instance with the specified second level domain label added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the second level domain label
     *
     * @param  ?string            $label
     * @throws ExceptionInterface If the second level domain label can not be added
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
    public function withPublicSuffix(EffectiveTLD $publicSuffix): self;

    /**
     * Returns a Domain object with a new resolve public suffix.
     *
     * The Public Suffix must be valid for the given domain name.
     * ex: if the domain name is www.ulb.ac.be the only valid public suffixes
     * are: be, ac.be, ulb.ac.be, or the null public suffix. Any other public
     * suffix will throw an Exception.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified Public Suffix Information.
     */
    public function resolve(EffectiveTLD $publicSuffix): self;
}
