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
    use IDNAConverterTrait;

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
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['publicSuffix']);
    }

    /**
     * New instance.
     *
     * @param string|null  $domain
     * @param PublicSuffix $publicSuffix
     */
    public function __construct($domain = null, PublicSuffix $publicSuffix = null)
    {
        if (false !== strpos((string) $domain, '%')) {
            $domain = rawurldecode($domain);
        }

        if (null !== $domain) {
            $domain = strtolower($domain);
        }

        $this->domain = $domain;
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * Filter the PublicSuffix
     *
     * @param PublicSuffix|null $publicSuffix
     *
     * @return PublicSuffix
     */
    private function setPublicSuffix(PublicSuffix $publicSuffix = null): PublicSuffix
    {
        if (null === $publicSuffix || null === $this->domain) {
            return new PublicSuffix();
        }

        return $publicSuffix;
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

        return $registrableDomain;
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

        return $subDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return array_merge([
            'domain' => $this->domain,
            'registrableDomain' => $this->registrableDomain,
            'subDomain' => $this->subDomain,
        ], $this->publicSuffix->jsonSerialize());
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return $this->jsonSerialize();
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
     * Tells whether the public suffix has been matching rule in a Public Suffix List.
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

    /**
     * Converts the domain to its IDNA ASCII form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with is content converted to its IDNA ASCII form
     *
     * @throws Exception if the domain can not be converted to ASCII using IDN UTS46 algorithm
     *
     * @return self
     */
    public function toAscii(): self
    {
        if (null === $this->domain || false !== strpos($this->domain, 'xn--')) {
            return $this;
        }

        return new self($this->idnToAscii($this->domain), $this->publicSuffix->toAscii());
    }

    /**
     * Converts the domain to its IDNA UTF8 form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with is content converted to its IDNA UTF8 form
     *
     * @throws Exception if the domain can not be converted to Unicode using IDN UTS46 algorithm
     *
     * @return self
     */
    public function toUnicode(): self
    {
        if (null === $this->domain || false === strpos($this->domain, 'xn--')) {
            return $this;
        }

        return new self($this->idnToUnicode($this->domain), $this->publicSuffix->toUnicode());
    }
}
