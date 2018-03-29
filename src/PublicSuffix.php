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
 * Public Suffix Value Object
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
final class PublicSuffix implements DomainInterface, JsonSerializable
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
     * New instance.
     *
     * @param string|null $publicSuffix
     * @param string      $section
     */
    public function __construct(string $publicSuffix = null, string $section = '')
    {
        list($this->publicSuffix, $this->labels) = $this->setDomain($publicSuffix);
        $this->section = $this->setSection($section);
    }

    /**
     * Set the public suffix section
     *
     * @param string $section
     *
     * @return string
     */
    private function setSection(string $section): string
    {
        if ('' === $this->publicSuffix || null === $this->publicSuffix) {
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
        return $this->publicSuffix;
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
        return Rules::ICANN_DOMAINS === $this->section;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List Private Section.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return Rules::PRIVATE_DOMAINS === $this->section;
    }

    /**
     * {@inheritdoc}
     */
    public function toAscii()
    {
        static $pattern = '/[^\x20-\x7f]/';
        if (null === $this->publicSuffix || !preg_match($pattern, $this->publicSuffix)) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $this->idnToAscii($this->publicSuffix);
        $clone->labels = array_reverse(explode('.', $clone->publicSuffix));

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
