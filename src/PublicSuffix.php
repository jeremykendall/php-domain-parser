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

use function count;
use const IDNA_DEFAULT;

final class PublicSuffix implements EffectiveTLD
{
    private DomainName $publicSuffix;

    private string $section;

    private function __construct(DomainName $publicSuffix, string $section)
    {
        if ('' === $publicSuffix->label(0)) {
            throw InvalidDomainName::dueToInvalidPublicSuffix($publicSuffix);
        }

        if (null === $publicSuffix->getContent()) {
            $section = '';
        }

        $this->publicSuffix = $publicSuffix;
        $this->section = $section;
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['publicSuffix'], $properties['section']);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromICANN($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(new Domain($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption), self::ICANN_DOMAINS);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromPrivate($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(new Domain($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption), self::PRIVATE_DOMAINS);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public static function fromUnknown($publicSuffix, int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(new Domain($publicSuffix, $asciiIDNAOption, $unicodeIDNAOption), '');
    }

    public static function fromNull(int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(Domain::fromNull($asciiIDNAOption, $unicodeIDNAOption), '');
    }

    public function getDomain(): DomainName
    {
        return $this->publicSuffix;
    }

    public function count(): int
    {
        return count($this->publicSuffix);
    }

    public function jsonSerialize(): ?string
    {
        return $this->publicSuffix->getContent();
    }

    public function getContent(): ?string
    {
        return $this->publicSuffix->getContent();
    }

    public function __toString(): string
    {
        return $this->publicSuffix->__toString();
    }

    public function getAsciiIDNAOption(): int
    {
        return $this->publicSuffix->getAsciiIDNAOption();
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->publicSuffix->getUnicodeIDNAOption();
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

    public function toAscii(): self
    {
        $clone = clone $this;
        $clone->publicSuffix = $this->publicSuffix->toAscii();

        return $clone;
    }

    public function toUnicode(): self
    {
        $clone = clone $this;
        $clone->publicSuffix = $this->publicSuffix->toUnicode();

        return $clone;
    }

    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->publicSuffix->getAsciiIDNAOption()) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $this->publicSuffix->withAsciiIDNAOption($option);

        return $clone;
    }

    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->publicSuffix->getUnicodeIDNAOption()) {
            return $this;
        }

        $clone = clone $this;
        $clone->publicSuffix = $this->publicSuffix->withUnicodeIDNAOption($option);

        return $clone;
    }
}
