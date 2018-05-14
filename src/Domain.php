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
use TypeError;

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
        $this->labels = $this->setLabels($domain);
        if (!empty($this->labels)) {
            $this->domain = implode('.', array_reverse($this->labels));
        }
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
            return $publicSuffix;
        }

        if (!$this->isResolvable()) {
            throw new Exception(sprintf('The domain `%s` can not contain a public suffix', $this->domain));
        }

        $publicSuffix = $this->normalize($publicSuffix);
        $psContent = $publicSuffix->getContent();
        if ($this->domain === $psContent) {
            throw new Exception(sprintf('The public suffix `%s` can not be equal to the domain name `%s`', $psContent, $this->domain));
        }

        if ('.'.$psContent !== substr($this->domain, - strlen($psContent) - 1)) {
            throw new Exception(sprintf('The public suffix `%s` can not be assign to the domain name `%s`', $psContent, $this->domain));
        }

        return $publicSuffix;
    }

    /**
     * Normalize the domain name encoding content.
     *
     * @param PublicSuffix $subject
     *
     * @return PublicSuffix
     */
    private function normalize(PublicSuffix $subject): PublicSuffix
    {
        if (null === $this->domain || null === $subject->getContent()) {
            return $subject;
        }

        static $pattern = '/[^\x20-\x7f]/';
        if (!preg_match($pattern, $this->domain)) {
            return $subject->toAscii();
        }

        return $subject->toUnicode();
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
        return $this->__debugInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
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
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->domain;
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
     * This method returns null if the registrable domain is equal to the public suffix.
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
     * This method returns null if the registrable domain is null
     * This method returns null if the registrable domain is equal to the public suffix
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
     * Tells whether the given domain can be resolved.
     *
     * A domain is resolvable if:
     *     - it contains at least 2 labels
     *     - it is not a absolute domain (end with a '.' character)
     *
     * @return bool
     */
    public function isResolvable(): bool
    {
        return 1 < count($this->labels) && '.' !== substr($this->domain, -1, 1);
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
        if (null === $this->domain) {
            return $this;
        }

        $domain = $this->idnToAscii($this->domain);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($domain, $this->publicSuffix);
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode()
    {
        if (null === $this->domain || false === strpos($this->domain, 'xn--')) {
            return $this;
        }

        return new self($this->idnToUnicode($this->domain), $this->publicSuffix);
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

        return new self($this->domain, $publicSuffix);
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
        if (null === $this->publicSuffix->getContent()) {
            throw new Exception('A subdomain can not be added to a domain without a public suffix part.');
        }

        $subDomain = $this->filterSubDomain($subDomain);
        $subLabels = [];
        if (null !== $subDomain) {
            static $pattern = '/[^\x20-\x7f]/';
            $method = !preg_match($pattern, $this->domain) ? 'idnToAscii' : 'idnToUnicode';

            $subDomain = $this->$method($subDomain);
            $subLabels = array_reverse(explode('.', $subDomain));
        }

        if ($this->subDomain === $subDomain) {
            return $this;
        }

        $labels = array_merge(
            array_slice($this->labels, 0, count($this->publicSuffix) + 1),
            $subLabels
        );

        return new self(implode('.', array_reverse($labels)), $this->publicSuffix);
    }

    /**
     * Filter a subdomain to update the domain part.
     *
     * @param mixed $subDomain
     *
     * @throws TypeError if the sub domain can not be converted
     *
     * @return string|null
     */
    private function filterSubDomain($subDomain)
    {
        if ($subDomain instanceof DomainInterface) {
            return $subDomain->getContent();
        }

        if (null === $subDomain) {
            return $subDomain;
        }

        if (is_scalar($subDomain) || method_exists($subDomain, '__toString')) {
            return (string) $subDomain;
        }

        throw new TypeError(sprintf('The label must be a scalar, a stringable object or NULL, `%s` given', gettype($subDomain)));
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
        if (null === $this->publicSuffix->getContent()) {
            throw new Exception('A public suffix can not be added to a domain without a public suffix part.');
        }

        if (!$publicSuffix instanceof PublicSuffix) {
            $publicSuffix = new PublicSuffix($publicSuffix);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $labels = array_merge(
            iterator_to_array($publicSuffix),
            array_slice($this->labels, count($this->publicSuffix))
        );

        return new self(implode('.', array_reverse($labels)), $publicSuffix);
    }

    /**
     * Appends a label to the domain.
     *
     * @see ::withLabel
     *
     * @param mixed $label
     *
     * @return self
     */
    public function prepend($label): self
    {
        return $this->withLabel(count($this->labels), $label);
    }

    /**
     * Prepends a label to the domain.
     *
     * @see ::withLabel
     *
     * @param mixed $label
     *
     * @return self
     */
    public function append($label): self
    {
        return $this->withLabel(- count($this->labels) - 1, $label);
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
        $nb_labels = count($this->labels);
        if ($key < - $nb_labels - 1 || $key > $nb_labels) {
            throw new Exception(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $key) {
            $key = $nb_labels + $key;
        }

        if (!is_scalar($label) && !method_exists($label, '__toString')) {
            throw new TypeError(sprintf('The label must be a scalar or a stringable object `%s` given', gettype($label)));
        }

        static $pattern = '/[^\x20-\x7f]/';
        $method = !preg_match($pattern, $this->domain) ? 'idnToAscii' : 'idnToUnicode';
        $label = $this->$method((string) $label);
        if (($this->labels[$key] ?? null) === $label) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;
        ksort($labels);

        return new self(
            implode('.', array_reverse($labels)),
            null === $this->publicSuffix->getLabel($key) ? $this->publicSuffix : null
        );
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
     * @param int ...$keys remaining keys to remove
     *
     * @throws Exception If the key is out of bounds
     *
     * @return self
     */
    public function withoutLabel(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_labels = count($this->labels);
        $mapper = function (int $key) use ($nb_labels): int {
            if (- $nb_labels > $key || $nb_labels - 1 < $key) {
                throw new Exception(sprintf('the key `%s` is invalid', $key));
            }

            if (0 > $key) {
                return $nb_labels + $key;
            }

            return $key;
        };

        $deleted_keys = array_keys(array_count_values(array_map($mapper, $keys)));
        $filter = function ($key) use ($deleted_keys): bool {
            return !in_array($key, $deleted_keys, true);
        };

        $labels = array_filter($this->labels, $filter, ARRAY_FILTER_USE_KEY);
        if (empty($labels)) {
            return new self();
        }

        $domain = implode('.', array_reverse(array_values($labels)));
        $psContent = $this->publicSuffix->getContent();
        if (null === $psContent || '.'.$psContent !== substr($domain, - strlen($psContent) - 1)) {
            return new self($domain);
        }

        return new self($domain, $this->publicSuffix);
    }
}
