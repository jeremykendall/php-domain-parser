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
 * Public Suffix Value Object.
 *
 * WARNING: "Some people use the PSL to determine what is a valid domain name
 * and what isn't. This is dangerous, particularly in these days where new
 * gTLDs are arriving at a rapid pace, if your software does not regularly
 * receive PSL updates, it will erroneously think new gTLDs are not
 * valid. The DNS is the proper source for this innormalizeion. If you must use
 * it for this purpose, please do not bake static copies of the PSL into your
 * software with no update mechanism."
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class PublicSuffix implements DomainInterface, JsonSerializable, PublicSuffixListSection
{
    use IDNAConverterTrait;

    /**
     * @var string|null
     */
    private $publicSuffix;

    /**
     * @var string
     */
    private $section;

    /**
     * @var string[]
     */
    private $labels;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['publicSuffix'], $properties['section']);
    }

    /**
     * Create an new instance from a Domain object.
     *
     * @param Domain $domain
     *
     * @return self
     */
    public static function createFromDomain(Domain $domain): self
    {
        $section = '';
        if ($domain->isICANN()) {
            $section = self::ICANN_DOMAINS;
        } elseif ($domain->isPrivate()) {
            $section = self::PRIVATE_DOMAINS;
        }

        return new self($domain->getPublicSuffix(), $section);
    }

    /**
     * New instance.
     *
     * @param mixed  $publicSuffix
     * @param string $section
     */
    public function __construct($publicSuffix = null, string $section = '')
    {
        $this->labels = $this->setLabels($publicSuffix);
        $this->publicSuffix = $this->setPublicSuffix();
        $this->section = $this->setSection($section);
    }

    /**
     * Set the public suffix content.
     *
     * @throws Exception if the public suffix labels are invalid
     *
     * @return string|null
     */
    private function setPublicSuffix()
    {
        if (empty($this->labels)) {
            return null;
        }

        $publicSuffix = implode('.', array_reverse($this->labels));
        if ('' !== reset($this->labels)) {
            return $publicSuffix;
        }

        throw new Exception(sprintf('The public suffix `%s` is invalid', $publicSuffix));
    }

    /**
     * Set the public suffix section.
     *
     * @param string $section
     *
     * @throws Exception if the submitted section is not supported
     *
     * @return string
     */
    private function setSection(string $section): string
    {
        static $section_list = [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, ''];
        if (!in_array($section, $section_list, true)) {
            throw new Exception(sprintf('`%s` is an unknown Public Suffix List section', $section));
        }

        if (null === $this->publicSuffix) {
            return '';
        }

        return $section;
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
            'publicSuffix' => $this->publicSuffix,
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
        return $this->publicSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->publicSuffix;
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
     * Tells whether the public suffix has a matching rule in a Public Suffix List.
     *
     * @return bool
     */
    public function isKnown(): bool
    {
        return '' !== $this->section;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List ICANN Section.
     *
     * @return bool
     */
    public function isICANN(): bool
    {
        return self::ICANN_DOMAINS === $this->section;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List Private Section.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return self::PRIVATE_DOMAINS === $this->section;
    }

    /**
     * {@inheritdoc}
     */
    public function toAscii()
    {
        if (null === $this->publicSuffix) {
            return $this;
        }

        $publicSuffix = $this->idnToAscii($this->publicSuffix);
        if ($publicSuffix === $this->publicSuffix) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $publicSuffix;
        $clone->labels = array_reverse(explode('.', $publicSuffix));

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode()
    {
        if (null === $this->publicSuffix || false === strpos($this->publicSuffix, 'xn--')) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $this->idnToUnicode($this->publicSuffix);
        $clone->labels = array_reverse(explode('.', $clone->publicSuffix));

        return $clone;
    }
}
