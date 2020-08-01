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

use JsonSerializable;

interface PublicSuffixList extends IDNConversion, JsonSerializable
{
    /**
     * Returns an array representation of the Public Suffix List Rules JSON serializable.
     */
    public function jsonSerialize(): array;

    /**
     * Determines the effective TLD against the PSL rules for cookie domain detection.
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getCookieEffectiveTLD(Host $domain): PublicSuffix;

    /**
     * Determines the effective TLD against the PSL rules for ICANN domain detection.
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getICANNEffectiveTLD(Host $domain): PublicSuffix;

    /**
     * Determines the effective TLD against the PSL rules for private domain detection.
     *
     * @throws ExceptionInterface If the effective TLD can not be resolve.
     */
    public function getPrivateEffectiveTLD(Host $domain): PublicSuffix;

    /**
     * Returns PSL info for a given domain.
     *
     * If the effective TLD can not be resolved it returns a null ResolvedDomainName
     */
    public function resolve(Host $domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @throws ExceptionInterface If the effective TLD can not be resolve.
     */
    public function resolveCookieDomain(Host $domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @throws ExceptionInterface If the effective TLD can not be resolve.
     */
    public function resolveICANNDomain(Host $domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @throws ExceptionInterface If the effective TLD can not be resolve.
     */
    public function resolvePrivateDomain(Host $domain): ResolvedDomainName;
}
