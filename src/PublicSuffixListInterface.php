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

/**
 * A class to resolve domain name against the Public Suffix list.
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
interface PublicSuffixListInterface extends JsonSerializable
{
    public const ICANN_DOMAINS = 'ICANN_DOMAINS';

    public const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    public function getAsciiIDNAOption(): int;

    public function getUnicodeIDNAOption(): int;

    public function jsonSerialize(): array;

    /**
     * Determines the public suffix for a given domain against the PSL rules for cookie domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getCookieEffectiveTLD(ResolvableHostInterface $domain): PublicSuffixInterface;

    /**
     * Determines the public suffix for a given domain against the PSL rules for ICANN domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getICANNEffectiveTLD(ResolvableHostInterface $domain): PublicSuffixInterface;

    /**
     * Determines the public suffix for a given domain against the PSL rules for private domain detection..
     *
     * @throws ExceptionInterface If the PublicSuffix can not be resolve.
     */
    public function getPrivateEffectiveTLD(ResolvableHostInterface $domain): PublicSuffixInterface;

    /**
     * Returns PSL info for a given domain.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolve($domain, string $section = ''): ResolvableHostInterface;

    /**
     * Returns PSL info for a given domain against the PSL rules for cookie domain detection.
     *
     * @param mixed $domain the domain value
     */
    public function resolveCookieDomain($domain): ResolvableHostInterface;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolveICANNDomain($domain): ResolvableHostInterface;

    /**
     * Returns PSL info for a given domain against the PSL rules for private domain detection.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     */
    public function resolvePrivateDomain($domain): ResolvableHostInterface;

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withAsciiIDNAOption(int $asciiIDNAOption): self;

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withUnicodeIDNAOption(int $unicodeIDNAOption): self;
}
