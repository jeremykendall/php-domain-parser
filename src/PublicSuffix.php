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
use function array_keys;
use function array_reverse;
use function count;
use function implode;
use function in_array;
use function reset;
use function sprintf;
use const IDNA_DEFAULT;

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

    private const PSL_SECTION = [self::PRIVATE_DOMAINS, self::ICANN_DOMAINS, ''];

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
        $infos = $this->parse($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption);
        $this->labels = $infos['labels'];
        $this->isTransitionalDifferent = $infos['isTransitionalDifferent'];
        $this->publicSuffix = $this->setPublicSuffix();
        $this->section = $this->setSection($section);
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['publicSuffix'],
            $properties['section'],
            $properties['asciiIDNAOption'] ?? IDNA_DEFAULT,
            $properties['unicodeIDNAOption'] ?? IDNA_DEFAULT
        );
    }

    /**
     * Create an new instance from a Domain object.
     * @param Domain $domain
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
     * Set the public suffix content.
     *
     * @throws InvalidDomain if the public suffix is invalid
     */
    private function setPublicSuffix(): ?string
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
     * @param  string                      $section
     * @throws CouldNotResolvePublicSuffix if the submitted section is not supported
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
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo(): array
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
    public function count(): int
    {
        return count($this->labels);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->publicSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return (string) $this->publicSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(int $key): ?string
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
     * Returns the object labels.
     */
    public function labels(): array
    {
        return $this->labels;
    }

    /**
     * Gets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getAsciiIDNAOption(): int
    {
        return $this->asciiIDNAOption;
    }

    /**
     * Gets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }

    /**
     * Returns true if domain contains deviation characters.
     *
     * @see http://unicode.org/reports/tr46/#Transition_Considerations
     */
    public function isTransitionalDifferent(): bool
    {
        return $this->isTransitionalDifferent;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List.
     */
    public function isKnown(): bool
    {
        return '' !== $this->section;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List ICANN Section.
     */
    public function isICANN(): bool
    {
        return self::ICANN_DOMAINS === $this->section;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List Private Section.
     */
    public function isPrivate(): bool
    {
        return self::PRIVATE_DOMAINS === $this->section;
    }

    /**
     * {@inheritdoc}
     */
    public function toAscii(): DomainInterface
    {
        if (null === $this->publicSuffix) {
            return $this;
        }

        $publicSuffix = $this->idnToAscii($this->publicSuffix, $this->asciiIDNAOption);
        if ($publicSuffix === $this->publicSuffix) {
            return $this;
        }

        return new self($publicSuffix, $this->section, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode(): DomainInterface
    {
        if (null === $this->publicSuffix || false === strpos($this->publicSuffix, 'xn--')) {
            return $this;
        }

        return new self(
            $this->idnToUnicode($this->publicSuffix, $this->unicodeIDNAOption),
            $this->section,
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
    }

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     * @param int $option
     */
    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->asciiIDNAOption) {
            return $this;
        }

        return new self($this->publicSuffix, $this->section, $option, $this->unicodeIDNAOption);
    }

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     * @param int $option
     */
    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->unicodeIDNAOption) {
            return $this;
        }

        return new self($this->publicSuffix, $this->section, $this->asciiIDNAOption, $option);
    }
}
