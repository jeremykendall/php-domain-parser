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
use Pdp\Exception\CouldNotResolvePublicSuffix;
use Pdp\Exception\InvalidDomain;
use Throwable;
use function array_keys;
use function array_reverse;
use function count;
use function explode;
use function implode;
use function in_array;
use function reset;
use function sprintf;

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
     * @internal
     */
    const PSL_SECTION = [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, ''];

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
     * @var int
     */
    private $asciiIDNAOption = IDNA_DEFAULT;
    
    /**
     * @var int
     */
    private $unicodeIDNAOption = IDNA_DEFAULT;
    
    /**
     * @var bool
     */
    private $isTransitionalDifferent;
    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['publicSuffix'],
            $properties['section'],
            $properties['asciiIDNAOption'],
            $properties['unicodeIDNAOption']
        );
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
        
        return new self(
            $domain->getPublicSuffix(),
            $section,
            $domain->getAsciiIDNAOption(),
            $domain->getUnicodeIDNAOption()
        );
    }

    /**
     * New instance.
     * @param mixed  $publicSuffix
     * @param string $section
     * @param int    $asciiIDNAOption
     * @param int    $unicodeIDNAOption
     */
    public function __construct(
        $publicSuffix = null,
        string $section = '',
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ) {
        $this->labels = $this->setLabels($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption);
        $this->publicSuffix = $this->setPublicSuffix();
        $this->section = $this->setSection($section);
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

    /**
     * Set the public suffix content.
     *
     * @throws InvalidDomain if the public suffix is invalid
     *
     * @return string|null
     */
    private function setPublicSuffix()
    {
        if ([] === $this->labels) {
            return null;
        }

        $publicSuffix = implode('.', array_reverse($this->labels));
        if ('' !== reset($this->labels)) {
            return $publicSuffix;
        }

        throw new InvalidDomain(sprintf('The public suffix `%s` is invalid', $publicSuffix));
    }

    /**
     * Set the public suffix section.
     *
     * @param string $section
     *
     * @throws CouldNotResolvePublicSuffix if the submitted section is not supported
     *
     * @return string
     */
    private function setSection(string $section): string
    {
        if (!in_array($section, self::PSL_SECTION, true)) {
            throw new CouldNotResolvePublicSuffix(sprintf('`%s` is an unknown Public Suffix List section', $section));
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

        $publicSuffix = $this->idnToAscii($this->publicSuffix, $this->asciiIDNAOption);
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
        $clone->publicSuffix = $this->idnToUnicode($this->publicSuffix, $this->unicodeIDNAOption);
        $clone->labels = array_reverse(explode('.', $clone->publicSuffix));

        return $clone;
    }
    
    public function getAsciiIDNAOption(): int
    {
        return $this->asciiIDNAOption;
    }
    
    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }
    /**
     * return true if domain contains deviation characters.
     * @see http://unicode.org/reports/tr46/#Transition_Considerations
     * @return bool
     **/
    public function isTransitionalDifferent(): bool
    {
        if ($this->isTransitionalDifferent === null) {
            try {
                $this->idnToAscii($this->getContent());
            } catch (Throwable $e) {
                $this->isTransitionalDifferent = false;
            }
        }
        return $this->isTransitionalDifferent;
    }
}
