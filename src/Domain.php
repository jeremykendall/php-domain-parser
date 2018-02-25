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

use Countable;
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
final class Domain implements Countable, JsonSerializable
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
        $this->domain = $this->setDomain($domain);
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * Normalize the given domain.
     *
     * @param string|null $domain
     *
     * @return string|null
     */
    private function setDomain(string $domain = null)
    {
        if (null === $domain) {
            return null;
        }

        if (false !== strpos($domain, '%')) {
            $domain = rawurldecode($domain);
        }

        return strtolower($domain);
    }

    /**
     * Sets the public suffix domain part.
     *
     * @param PublicSuffix|null $publicSuffix
     *
     * @return PublicSuffix
     */
    private function setPublicSuffix(PublicSuffix $publicSuffix = null): PublicSuffix
    {
        $publicSuffix = $publicSuffix ?? new PublicSuffix();
        if (null === $publicSuffix->getContent()) {
            return $publicSuffix;
        }

        if (null === $this->domain || false === strpos($this->domain, '.')) {
            return new PublicSuffix();
        }

        return $publicSuffix;
    }

    /**
     * Computes the registrable domain part.
     *
     * @return string|null
     */
    private function setRegistrableDomain()
    {
        if (null === $this->publicSuffix->getContent()) {
            return null;
        }

        $labels = explode('.', $this->domain);
        $countLabels = count($labels);
        $countPublicSuffixLabels = count($this->publicSuffix);
        if ($countLabels === $countPublicSuffixLabels) {
            return null;
        }

        return implode('.', array_slice($labels, $countLabels - $countPublicSuffixLabels - 1));
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

        $labels = explode('.', $this->domain);
        $countLabels = count($labels);
        $countLabelsToRemove = count(explode('.', $this->registrableDomain));
        if ($countLabels === $countLabelsToRemove) {
            return null;
        }

        return implode('.', array_slice($labels, 0, $countLabels - $countLabelsToRemove));
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
     * {@inheritdoc}
     */
    public function count()
    {
        if (null === $this->domain) {
            return 0;
        }

        return count(explode('.', $this->domain));
    }

    /**
     * Returns the domain content.
     *
     * This method should return null on seriously malformed domain name
     *
     * @return string|null
     */
    public function getContent()
    {
        return $this->domain;
    }

    /**
     * Returns the full domain name.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.3
     * @see Domain::getContent
     *
     * This method should return null on seriously malformed domain name
     *
     * @return string|null
     */
    public function getDomain()
    {
        return $this->getContent();
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

        $newDomain = $this->idnToAscii($this->domain);
        if ($newDomain === $this->domain) {
            return $this;
        }

        return new self($newDomain, $this->publicSuffix->toAscii());
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
