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
    public function jsonSerialize(): array;

    /**
     * Determines the public suffix for a given domain against the PSL rules for cookie domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getCookieEffectiveTLD(ResolvedDomainName $domain): PublicSuffix;

    /**
     * Determines the public suffix for a given domain against the PSL rules for ICANN domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getICANNEffectiveTLD(ResolvedDomainName $domain): PublicSuffix;

    /**
     * Determines the public suffix for a given domain against the PSL rules for private domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getPrivateEffectiveTLD(ResolvedDomainName $domain): PublicSuffix;

    /**
     * Returns PSL info for a given domain.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolve(Host $domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @param mixed $domain the domain value
     */
    public function resolveCookieDomain($domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolveICANNDomain($domain): ResolvedDomainName;

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolvePrivateDomain($domain): ResolvedDomainName;
}
