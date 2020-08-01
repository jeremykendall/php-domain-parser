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

use function array_reverse;
use function count;
use function implode;
use function reset;
use const IDNA_DEFAULT;

final class PublicSuffix extends DomainNameParser implements EffectiveTLD
{
    private ?string $publicSuffix;

    private string $section;

    private int $labelsCount;

    private int $asciiIDNAOption;

    private int $unicodeIDNAOption;

    /**
     * @param mixed $publicSuffix a public suffix in a type that is supported
     */
    private function __construct(
        $publicSuffix,
        string $section,
        int $asciiIDNAOption,
        int $unicodeIDNAOption
    ) {
        $this->setPublicSuffix($this->parse($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption));
        $this->section = '';
        if (null !== $this->publicSuffix) {
            $this->section = $section;
        }

        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

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
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromICANNSection($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self($publicSuffix, self::ICANN_DOMAINS, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromPrivateSection($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self($publicSuffix, self::PRIVATE_DOMAINS, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromUnknownSection($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self($publicSuffix, '', $asciiIDNAOption, $unicodeIDNAOption);
    }

    public static function fromNull(int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(null, '', $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * Set the public suffix content.
     *
     * @throws InvalidDomainName if the public suffix is invalid
     */
    private function setPublicSuffix(array $labels): void
    {
        if ([] === $labels) {
            $this->publicSuffix = null;
            $this->labelsCount = 0;

            return;
        }

        $publicSuffix = implode('.', array_reverse($labels));
        if ('' !== reset($labels)) {
            $this->publicSuffix = $publicSuffix;
            $this->labelsCount = count($labels);

            return;
        }

        throw InvalidDomainName::dueToInvalidPublicSuffix($publicSuffix);
    }

    public function count(): int
    {
        return $this->labelsCount;
    }

    public function jsonSerialize(): ?string
    {
        return $this->getContent();
    }

    public function getContent(): ?string
    {
        return $this->publicSuffix;
    }

    public function __toString(): string
    {
        return (string) $this->publicSuffix;
    }

    public function getAsciiIDNAOption(): int
    {
        return $this->asciiIDNAOption;
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }

    public function isKnown(): bool
    {
        return '' !== $this->section;
    }

    public function isICANN(): bool
    {
        return self::ICANN_DOMAINS === $this->section;
    }

    public function isPrivate(): bool
    {
        return self::PRIVATE_DOMAINS === $this->section;
    }

    public function toAscii(): Host
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

    public function toUnicode(): Host
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

    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->asciiIDNAOption) {
            return $this;
        }

        return new self($this->publicSuffix, $this->section, $option, $this->unicodeIDNAOption);
    }

    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->unicodeIDNAOption) {
            return $this;
        }

        return new self($this->publicSuffix, $this->section, $this->asciiIDNAOption, $option);
    }
}
