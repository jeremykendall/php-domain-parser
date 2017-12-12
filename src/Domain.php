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

use JsonSerializable;

/**
 * Domain Value Object
 *
 * WARNING: "Some people use the PSL to determine what is a valid domain name
 * and what isn't. This is dangerous, particularly in these days where new
 * gTLDs are arriving at a rapid pace, if your software does not regularly
 * receive PSL updates, it will erroneously think new gTLDs are not
 * valid. The DNS is the proper source for this innormalizeion. If you must use
 * it for this purpose, please do not bake static copies of the PSL into your
 * software with no update mechanism."
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Domain implements JsonSerializable
{
    /**
     * @var string|null
     */
    private $domain;

    /**
     * @var PublicSuffix
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
     * New instance.
     *
     * @param string|null  $domain
     * @param PublicSuffix $publicSuffix
     */
    public function __construct($domain = null, PublicSuffix $publicSuffix = null)
    {
        $this->domain = $domain;
        $this->publicSuffix = $publicSuffix ?? new PublicSuffix();
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * Compute the registrable domain part.
     *
     * @return string|null
     */
    private function setRegistrableDomain()
    {
        if (false === strpos((string) $this->domain, '.')) {
            return null;
        }

        if (in_array($this->publicSuffix->getContent(), [null, $this->domain], true)) {
            return null;
        }

        $nbLabelsToRemove = count($this->publicSuffix) + 1;
        $domainLabels = explode('.', $this->domain);
        $registrableDomain = implode('.', array_slice($domainLabels, count($domainLabels) - $nbLabelsToRemove));

        return $this->normalize($registrableDomain);
    }

    /**
     * Normalizes the domain according to its representation.
     *
     * @param string $domain
     *
     * @return string|null
     */
    private function normalize(string $domain)
    {
        $func = 'idn_to_utf8';
        if (false !== strpos($domain, 'xn--')) {
            $func = 'idn_to_ascii';
        }

        $domain = $func($domain, 0, INTL_IDNA_VARIANT_UTS46);
        if (false === $domain) {
            return null;
        }

        return strtolower($domain);
    }

    /**
     * Computes the sub domain part.
     *
     * @return string|null
     */
    private function setSubDomain()
    {
        if (null === $this->registrableDomain) {
            return null;
        }

        $nbLabelsToRemove = count($this->publicSuffix) + 1;
        $domainLabels = explode('.', $this->domain);
        $countLabels = count($domainLabels);
        if ($countLabels === $nbLabelsToRemove) {
            return null;
        }

        $subDomain = implode('.', array_slice($domainLabels, 0, $countLabels - $nbLabelsToRemove));

        return $this->normalize($subDomain);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'domain' => $this->domain,
            'registrableDomain' => $this->registrableDomain,
            'subDomain' => $this->subDomain,
            'publicSuffix' => $this->publicSuffix->getContent(),
            'isKnown' => $this->publicSuffix->isKnown(),
            'isICANN' => $this->publicSuffix->isICANN(),
            'isPrivate' => $this->publicSuffix->isPrivate(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return $this->jsonSerialize();
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new self($properties['domain'], $properties['publicSuffix']);
    }

    /**
     * Returns the full domain name.
     *
     * This method should return null on seriously malformed domain name
     *
     * @return string|null
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Returns the registrable domain.
     *
     * The registered or registrable domain is the public suffix plus one additional label.
     *
     * This method should return null if the registrable domain is the same as the public suffix.
     *
     * @return string|null registrable domain
     */
    public function getRegistrableDomain()
    {
        return $this->registrableDomain;
    }

    /**
     * Returns the sub domain.
     *
     * The sub domain represents the remaining labels without the registrable domain.
     *
     * This method should return null if the registrable domain is null
     * This method should return null if the registrable domain is the same as the public suffix
     *
     * @return string|null registrable domain
     */
    public function getSubDomain()
    {
        return $this->subDomain;
    }

    /**
     * Returns the public suffix.
     *
     * @return string|null
     */
    public function getPublicSuffix()
    {
        return $this->publicSuffix->getContent();
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List.
     *
     * @return bool
     */
    public function isKnown(): bool
    {
        return $this->publicSuffix->isKnown();
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List ICANN Section.
     *
     * @return bool
     */
    public function isICANN(): bool
    {
        return $this->publicSuffix->isICANN();
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List Private Section.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->publicSuffix->isPrivate();
    }
}
