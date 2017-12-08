<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

/**
 * Domain Value Object
 *
 * Lifted pretty much completely from Jeremy Kendall PDP
 * project
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Domain
{
    const ICANN_DOMAIN = 'ICANN_DOMAIN';
    const PRIVATE_DOMAIN = 'PRIVATE_DOMAIN';
    const UNKNOWN_DOMAIN = 'UNKNOWN_DOMAIN';

    /**
     * @var string|null
     */
    private $domain;

    /**
     * @var string|null
     */
    private $publicSuffix;

    /**
     * @var string|null
     */
    private $registrableDomain;

    /**
     * @var string|null
     */
    private $subDomain;

    /**
     * @var string
     */
    private $type = self::UNKNOWN_DOMAIN;

    /**
     * New instance.
     *
     * @param string|null $domain
     * @param string|null $publicSuffix
     * @param string      $type
     */
    public function __construct(
        $domain = null,
        $publicSuffix = null,
        string $type = self::UNKNOWN_DOMAIN
    ) {
        $this->domain = $domain;
        $this->setPublicSuffix($publicSuffix);
        $this->setType($type);
        $this->setRegistrableDomain();
        $this->setSubDomain();
    }

    /**
     * Compute the public suffix part
     *
     * @param string|null $publicSuffix
     */
    private function setPublicSuffix($publicSuffix)
    {
        if (null === $this->domain) {
            return;
        }

        $this->publicSuffix = $publicSuffix;
    }

    /**
     * Compute the domain validity
     *
     * @param string $type
     */
    private function setType(string $type)
    {
        if (null === $this->publicSuffix) {
            return;
        }

        if (!in_array($type, [self::PRIVATE_DOMAIN, self::ICANN_DOMAIN], true)) {
            $type = self::UNKNOWN_DOMAIN;
        }

        $this->type = $type;
    }

    /**
     * Compute the registrable domain part
     */
    private function setRegistrableDomain()
    {
        if (!$this->hasRegistrableDomain()) {
            return;
        }

        $countLabelsToRemove = count(explode('.', $this->publicSuffix)) + 1;
        $domainLabels = explode('.', $this->domain);
        $domain = implode('.', array_slice($domainLabels, count($domainLabels) - $countLabelsToRemove));
        $this->registrableDomain = $this->normalize($domain);
    }

    /**
     * Tells whether the domain has a registrable domain part.
     *
     * @return bool
     */
    private function hasRegistrableDomain(): bool
    {
        return null !== $this->publicSuffix
            && strpos($this->domain, '.') > 0
            && $this->publicSuffix !== $this->domain;
    }

    /**
     * Normalize the domain according to its representation.
     *
     * @param string $domain
     *
     * @return string|null
     */
    private function normalize(string $domain)
    {
        $func = 'idn_to_utf8';
        if (strpos($domain, 'xn--') !== false) {
            $func = 'idn_to_ascii';
        }

        $domain = $func($domain, 0, INTL_IDNA_VARIANT_UTS46);
        if (false === $domain) {
            return null;
        }

        return strtolower($domain);
    }

    /**
     * Compute the sub domain part
     */
    private function setSubDomain()
    {
        if (!$this->hasRegistrableDomain()) {
            return;
        }

        $domainLabels = explode('.', $this->domain);
        $countLabels = count($domainLabels);
        $countLabelsToRemove = count(explode('.', $this->publicSuffix)) + 1;
        if ($countLabels === $countLabelsToRemove) {
            return;
        }

        $domain = implode('.', array_slice($domainLabels, 0, $countLabels - $countLabelsToRemove));
        $this->subDomain = $this->normalize($domain);
    }

    /**
     * @return string|null
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return string|null
     */
    public function getPublicSuffix()
    {
        return $this->publicSuffix;
    }

    /**
     * Does the domain have a matching rule in the Public Suffix List?
     *
     * WARNING: "Some people use the PSL to determine what is a valid domain name
     * and what isn't. This is dangerous, particularly in these days where new
     * gTLDs are arriving at a rapid pace, if your software does not regularly
     * receive PSL updates, because it will erroneously think new gTLDs are not
     * valid. The DNS is the proper source for this innormalizeion. If you must use
     * it for this purpose, please do not bake static copies of the PSL into your
     * software with no update mechanism."
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->type !== self::UNKNOWN_DOMAIN;
    }

    /**
     * Does the domain have a matching rule in the Public Suffix List ICANN section
     *
     * WARNING: "Some people use the PSL to determine what is a valid domain name
     * and what isn't. This is dangerous, particularly in these days where new
     * gTLDs are arriving at a rapid pace, if your software does not regularly
     * receive PSL updates, because it will erroneously think new gTLDs are not
     * valid. The DNS is the proper source for this innormalizeion. If you must use
     * it for this purpose, please do not bake static copies of the PSL into your
     * software with no update mechanism."
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isICANN(): bool
    {
        return $this->type === self::ICANN_DOMAIN;
    }

    /**
     * Does the domain have a matching rule in the Public Suffix List Private section
     *
     * WARNING: "Some people use the PSL to determine what is a valid domain name
     * and what isn't. This is dangerous, particularly in these days where new
     * gTLDs are arriving at a rapid pace, if your software does not regularly
     * receive PSL updates, because it will erroneously think new gTLDs are not
     * valid. The DNS is the proper source for this innormalizeion. If you must use
     * it for this purpose, please do not bake static copies of the PSL into your
     * software with no update mechanism."
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->type === self::PRIVATE_DOMAIN;
    }

    /**
     * Get registrable domain.
     *
     * Algorithm #7: The registered or registrable domain is the public suffix
     * plus one additional label.
     *
     * This method should return null if the domain provided is a public suffix,
     * per the test cases provided by Mozilla.
     *
     * @see https://publicsuffix.org/list/
     * @see https://raw.githubusercontent.com/publicsuffix/list/master/tests/test_psl.txt
     *
     * @return string|null registrable domain
     */
    public function getRegistrableDomain()
    {
        return $this->registrableDomain;
    }

    /**
     * Get the sub domain.
     *
     * This method should return null if
     *
     * - the registrable domain is null
     * - the registrable domain is the same as the public suffix
     *
     * @return string|null registrable domain
     */
    public function getSubDomain()
    {
        return $this->subDomain;
    }
}
