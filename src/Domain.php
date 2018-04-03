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
 * Domain Value Object.
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
     * @param mixed        $domain
     * @param PublicSuffix $publicSuffix
     */
    public function __construct($domain = null, PublicSuffix $publicSuffix = null)
    {
        list($this->domain, $this->labels) = $this->setDomain($domain);
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix ?? new PublicSuffix());
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * Sets the public suffix domain part.
     *
     * @param PublicSuffix $publicSuffix
     *
     * @throws Exception If the domain can not contain a public suffix
     * @throws Exception If the domain value is the same as the public suffix value
     * @throws Exception If the domain can not be match with the public suffix
     *
     * @return PublicSuffix
     */
    private function setPublicSuffix(PublicSuffix $publicSuffix): PublicSuffix
    {
        if (null === $publicSuffix->getContent()) {
            return new PublicSuffix();
        }

        if (null === $this->domain || false === strpos($this->domain, '.')) {
            throw new Exception(sprintf('The domain `%s` can not contain a public suffix', $this->domain));
        }

        $publicSuffix = $this->normalize($publicSuffix);
        $publicSuffixContent = $publicSuffix->getContent();
        if ($this->domain === $publicSuffixContent) {
            throw new Exception(sprintf('The public suffix `%s` can not be equal to the domain name `%s`', $publicSuffixContent, $this->domain));
        }

        if ('.'.$publicSuffixContent !== substr($this->domain, - strlen($publicSuffixContent) - 1)) {
            throw new Exception(sprintf('The public suffix `%s` can not be assign to the domain name `%s`', $publicSuffixContent, $this->domain));
        }

        return $publicSuffix;
    }

    /**
     * Normalize the domain name encoding content.
     *
     * @param mixed $domain
     *
     * @return mixed
     */
    private function normalize($domain)
    {
        static $pattern = '/[^\x20-\x7f]/';
        if (null !== $this->domain && preg_match($pattern, $this->domain)) {
            return $domain->toUnicode();
        }

        return $domain->toAscii();
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain()
    {
        if (null === $this->publicSuffix->getContent()) {
            return null;
        }

        return implode('.', array_slice(
            explode('.', $this->domain),
            count($this->labels) - count($this->publicSuffix) - 1
        ));
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

        $nbLabels = count($this->labels);
        $nbRegistrableLabels = count($this->publicSuffix) + 1;
        if ($nbLabels === $nbRegistrableLabels) {
            return null;
        }

        return implode('.', array_slice(
            explode('.', $this->domain),
            0,
            $nbLabels - $nbRegistrableLabels
        ));
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
     * Returns a Domain object with a new resolve public suffix.
     *
     * The Public Suffix must be valid for the given domain name.
     * ex: if the domain name is www.ulb.ac.be the only valid public suffixes
     * are: be, ac.be, ulb.ac.be, or the null public suffix. Any other public
     * suffix will throw an Exception.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified Public Suffix Information.
     *
     * @param mixed $publicSuffix
     *
     * @return self
     */
    public function resolve($publicSuffix): self
    {
        if (!$publicSuffix instanceof PublicSuffix) {
            $publicSuffix = new PublicSuffix($publicSuffix);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $clone->setPublicSuffix($publicSuffix);
        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }

    /**
     * Returns an instance with the specified sub domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new sub domain
     *
     * @param mixed $subDomain the subdomain to add
     *
     * @throws Exception If the Sub domain is invalid or can not be added to the current Domain
     *
     * @return self
     */
    public function withSubDomain($subDomain): self
    {
        if (!$subDomain instanceof PublicSuffix) {
            $subDomain = new PublicSuffix($subDomain);
        }

        if (null === $this->publicSuffix->getContent()) {
            throw new Exception('A subdomain can not be added to a domain without a public suffix part.');
        }

        $subDomain = $this->normalize($subDomain);
        if ($this->subDomain === $subDomain->getContent()) {
            return $this;
        }

        $clone = clone $this;
        $clone->labels = array_merge(
            array_slice($this->labels, 0, count($this->publicSuffix) + 1),
            iterator_to_array($subDomain)
        );
        $clone->domain = implode('.', array_reverse($clone->labels));
        $clone->subDomain = $subDomain->getContent();

        return $clone;
    }

    /**
     * Returns an instance with the specified public suffix added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new public suffix
     *
     * @param mixed $publicSuffix
     *
     * @throws Exception If the public suffix is invalid or can not be added to the current Domain
     *
     * @return self
     */
    public function withPublicSuffix($publicSuffix): self
    {
        if (!$publicSuffix instanceof PublicSuffix) {
            $publicSuffix = new PublicSuffix($publicSuffix);
        }

        if (null === $this->publicSuffix->getContent()) {
            throw new Exception('A public suffix can not be added to domain without a public suffix.');
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $clone = clone $this;
        $clone->labels = array_merge(
            iterator_to_array($publicSuffix),
            array_slice($this->labels, count($this->publicSuffix))
        );
        $clone->domain = implode('.', array_reverse($clone->labels));
        $clone->publicSuffix = $publicSuffix;
        $clone->registrableDomain = $this->labels[count($this->publicSuffix)].'.'.$publicSuffix->getContent();

        return $clone;
    }

    /**
     * Returns an instance with the specified label added at the specified key.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new label
     *
     * If $key is non-negative, the added label will be the label at $key position from the start.
     * If $key is negative, the added label will be the label at $key position from the end.
     *
     * @param int   $key
     * @param mixed $label
     *
     * @throws Exception If the key is out of bounds
     * @throws Exception If the label is invalid
     *
     * @return self
     */
    public function withLabel(int $key, $label): self
    {
        if (!$label instanceof PublicSuffix) {
            $label = new PublicSuffix($label);
        }

        if (1 !== count($label)) {
            throw new Exception(sprintf('The label `%s` is invalid', $label->getContent()));
        }

        $nb_labels = count($this->labels);
        $offset = filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_labels - 1, 'max_range' => $nb_labels]]);
        if (false === $offset) {
            throw new Exception(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $offset) {
            $offset = $nb_labels + $offset;
        }

        $label = $this->normalize($label)->getContent();
        if ($label === ($this->labels[$offset] ?? null)) {
            return $this;
        }

        $clone = clone $this;
        $clone->labels[$offset] = $label;
        ksort($clone->labels);
        $clone->labels = array_values($clone->labels);
        $clone->domain = implode('.', array_reverse($clone->labels));
        if (null !== $this->publicSuffix->getLabel($offset)) {
            $clone->publicSuffix = new PublicSuffix();
            $clone->registrableDomain = null;
            $clone->subDomain = null;

            return $clone;
        }

        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }

    /**
     * Returns an instance with the label at the specified key removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the specified label
     *
     * If $key is non-negative, the removed label will be the label at $key position from the start.
     * If $key is negative, the removed label will be the label at $key position from the end.
     *
     * @param int $key
     *
     * @throws Exception If the key is out of bounds
     *
     * @return self
     */
    public function withoutLabel(int $key): self
    {
        $nb_labels = count($this->labels);
        $offset = filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_labels, 'max_range' => $nb_labels - 1]]);
        if (false === $offset) {
            throw new Exception(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $offset) {
            $offset = $nb_labels + $offset;
        }

        $clone = clone $this;
        unset($clone->labels[$offset]);
        $clone->domain = implode('.', array_reverse($clone->labels));
        if (null !== $this->publicSuffix->getLabel($offset)) {
            $clone->publicSuffix = new PublicSuffix();
            $clone->registrableDomain = null;
            $clone->subDomain = null;

            return $clone;
        }

        $clone->registrableDomain = $clone->setRegistrableDomain();
        $clone->subDomain = $clone->setSubDomain();

        return $clone;
    }
}
