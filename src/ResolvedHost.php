<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

interface ResolvedHost extends Host
{
    public function getHost(): DomainName;

    public function getPublicSuffix(): PublicSuffix;

    public function getSubDomain(): DomainName;

    public function getRegistrableDomain(): DomainName;

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
     * Returns an instance with the specified public suffix added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new public suffix
     *
     * If the domain already has a public suffix it will be replaced by the new value
     * otherwise the public suffix content is added to or remove from the current domain.
     */
    public function withPublicSuffix(PublicSuffix $publicSuffix): self;

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
    public function resolve(PublicSuffix $publicSuffix): self;
}
