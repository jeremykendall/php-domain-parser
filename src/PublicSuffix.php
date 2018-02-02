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
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class PublicSuffix implements Countable, JsonSerializable
{
    /**
     * @var string|null
     */
    private $publicSuffix;

    /**
     * @var string
     */
    private $section;

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
        $this->publicSuffix = $publicSuffix;
        $this->section = $section;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'publicSuffix' => $this->getContent(),
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
     * Returns the public suffix content.
     *
     * @return string|null
     */
    public function getContent()
    {
        return $this->publicSuffix;
    }

    /**
     * Returns the public suffix section name used to determine the public suffix.
     *
     * @return string
     */
    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (null === $this->publicSuffix) {
            return 0;
        }

        return count(explode('.', $this->publicSuffix));
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
        if (null === $this->publicSuffix) {
            return $this;
        }

        $publicSuffix = idn_to_utf8($this->publicSuffix, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (!$arr['errors']) {
            $clone = clone $this;
            $clone->publicSuffix = $publicSuffix;

            return $clone;
        }

        throw new Exception(sprintf('The following public suffix `%s` can not be converted to unicode', $this->publicSuffix));
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
        if (null === $this->publicSuffix) {
            return $this;
        }

        $publicSuffix = idn_to_ascii($this->publicSuffix, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (!$arr['errors']) {
            $clone = clone $this;
            $clone->publicSuffix = $publicSuffix;

            return $clone;
        }

        throw new Exception(sprintf('The following public suffix `%s` can not be converted to ascii', $this->publicSuffix));
    }
}
