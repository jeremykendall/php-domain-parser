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
final class Domain implements DomainInterface, JsonSerializable
{
    use IDNAConverterTrait;

    /**
     * @var string|null
     */
    private $domain;

    /**
     * @var string[]
     */
    private $labels;

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
    public function __construct(string $domain = null, PublicSuffix $publicSuffix = null)
    {
        list($this->domain, $this->labels) = $this->setDomain($domain);
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix);
        $this->assertValidState();
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
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
        if (null === $publicSuffix
            || null === $this->domain
            || false === strpos($this->domain, '.')
            || count($this->labels) === count($publicSuffix)
        ) {
            return new PublicSuffix();
        }

        return $publicSuffix;
    }

    /**
     * assert the domain internal state is valid
     *
     * @throws Exception if the public suffix does not match the domain
     */
    protected function assertValidState()
    {
        foreach ($this->publicSuffix as $offset => $label) {
            if ($label !== $this->labels[$offset]) {
                throw new Exception(sprintf('The public suffix `%s` is invalid for the domain `%s`', $this->publicSuffix->getContent(), $this->domain));
            }
        }
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain()
    {
        if (null === $this->publicSuffix->getContent()) {
            return null;
        }

        $labels = explode('.', $this->domain);
        $countLabels = count($labels);
        $countPublicSuffixLabels = count($this->publicSuffix);

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
    public function getIterator()
    {
        foreach ($this->labels as $offset => $label) {
            yield $label;
        }
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
            'isKnown' => $this->isKnown(),
            'isICANN' => $this->isICANN(),
            'isPrivate' => $this->isPrivate(),
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
    public function count()
    {
        return count($this->labels);
    }

    /**
     * {@inheritdoc}
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
     * @deprecated 5.3 deprecated
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
     * {@inheritdoc}
     */
    public function getLabel(int $key)
    {
        if ($key < 0) {
            $key += count($this->labels);
        }

        return $this->labels[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function keys(string $label): array
    {
        return array_keys($this->labels, $label, true);
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

    /**
     * {@inheritdoc}
     */
    public function toAscii()
    {
        static $pattern = '/[^\x20-\x7f]/';
        if (null === $this->domain || !preg_match($pattern, $this->domain)) {
            return $this;
        }

        $clone = clone $this;
        $clone->domain = $this->idnToAscii($this->domain);
        $clone->labels = array_reverse(explode('.', $clone->domain));
        $clone->publicSuffix = $this->publicSuffix->toAscii();
        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode()
    {
        if (null === $this->domain || false === strpos($this->domain, 'xn--')) {
            return $this;
        }

        $clone = clone $this;
        $clone->domain = $this->idnToUnicode($this->domain);
        $clone->labels = array_reverse(explode('.', $clone->domain));
        $clone->publicSuffix = $this->publicSuffix->toUnicode();
        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }

    /**
     * Returns a new domain name with a different public suffix.
     *
     * @param PublicSuffix $publicSuffix
     *
     * @throws Exception if the domain can not contain a public suffix
     * @throws Exception if the public suffix can not be assign to the domain name
     *
     * @return self
     */
    public function withPublicSuffix(PublicSuffix $publicSuffix): self
    {
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        if (null === $publicSuffix->getContent()) {
            $clone = clone $this;
            $clone->publicSuffix = $publicSuffix;
            $clone->registrableDomain = $clone->setRegistrableDomain();
            $clone->subDomain = $clone->setSubDomain();

            return $clone;
        }

        if (null === $this->domain || false === strpos($this->domain, '.')) {
            throw new Exception(sprintf('The domain `%s` can not contain a public suffix', $this->domain));
        }

        static $pattern = '/[^\x20-\x7f]/';
        if (preg_match($pattern, $this->domain)) {
            $publicSuffix = $publicSuffix->toUnicode();
        }

        $publicSuffixContent = $publicSuffix->getContent();
        if ($this->domain === $publicSuffixContent || $publicSuffixContent !== substr($this->domain, - strlen($publicSuffixContent))) {
            throw new Exception(sprintf('the public suffix `%s` can not be assign to the domain name `%s`', $publicSuffixContent, $this->domain));
        }

        $clone = clone $this;
        $clone->publicSuffix = $publicSuffix;
        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }
}
