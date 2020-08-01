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

use TypeError;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_unshift;
use function count;
use function gettype;
use function implode;
use function in_array;
use function ksort;
use function preg_match;
use function sprintf;
use function strpos;
use const IDNA_DEFAULT;

final class Domain extends DomainNameParser implements DomainName
{
    private const REGEXP_IDN_PATTERN = '/[^\x20-\x7f]/';

    /**
     * @var array<string>
     */
    private array $labels;

    private ?string $domain;

    private int $asciiIDNAOption;

    private int $unicodeIDNAOption;

    /**
     * @param null|mixed $domain
     */
    public function __construct(
        $domain = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ) {
        $this->labels = $this->parse($domain, $asciiIDNAOption, $unicodeIDNAOption);
        $this->domain = implode('.', array_reverse($this->labels));
        if ([] === $this->labels) {
            $this->domain = null;
        }
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['domain'],
            $properties['asciiIDNAOption'],
            $properties['unicodeIDNAOption']
        );
    }

    public static function fromNull(int $asciiIDNAOption = IDNA_DEFAULT, int $unicodeIDNAOption = IDNA_DEFAULT): self
    {
        return new self(null, $asciiIDNAOption, $unicodeIDNAOption);
    }

    public function getIterator()
    {
        foreach ($this->labels as $offset => $label) {
            yield $label;
        }
    }

    public function jsonSerialize(): ?string
    {
        return $this->getContent();
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function getContent(): ?string
    {
        return $this->domain;
    }

    public function __toString(): string
    {
        return (string) $this->domain;
    }

    public function label(int $key): ?string
    {
        if ($key < 0) {
            $key += count($this->labels);
        }

        return $this->labels[$key] ?? null;
    }

    public function keys(string $label): array
    {
        return array_keys($this->labels, $label, true);
    }

    public function labels(): array
    {
        return $this->labels;
    }

    public function getAsciiIDNAOption(): int
    {
        return $this->asciiIDNAOption;
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }

    public function toAscii(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = $this->idnToAscii($this->domain, $this->asciiIDNAOption);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    public function toUnicode(): self
    {
        if (null === $this->domain || false === strpos($this->domain, 'xn--')) {
            return $this;
        }

        return new self(
            $this->idnToUnicode($this->domain, $this->unicodeIDNAOption),
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
    }

    /**
     * Filter a subdomain to update the domain part.
     *
     * @param string|object $domain a domain
     *
     * @throws TypeError if the domain can not be converted
     */
    private function normalizeContent($domain): string
    {
        if ($domain instanceof Host) {
            $domain = $domain->getContent();
        }

        if (null === $domain || (!is_string($domain) && !method_exists($domain, '__toString'))) {
            throw new TypeError(sprintf('The label must be a '.Host::class.', a stringable object or a string, `%s` given', gettype($domain)));
        }

        $domain = (string) $domain;
        if (null === $this->domain) {
            return $domain;
        }

        if (1 === preg_match(self::REGEXP_IDN_PATTERN, $this->domain)) {
            return $this->idnToUnicode($domain, $this->unicodeIDNAOption);
        }

        return $this->idnToAscii($domain, $this->asciiIDNAOption);
    }

    public function prepend($label): self
    {
        return $this->withLabel(count($this->labels), $label);
    }

    public function append($label): self
    {
        return $this->withLabel(- count($this->labels) - 1, $label);
    }

    public function withLabel(int $key, $label): self
    {
        $nb_labels = count($this->labels);
        if ($key < - $nb_labels - 1 || $key > $nb_labels) {
            throw InvalidDomainName::dueToInvalidLabelKey($key);
        }

        if (0 > $key) {
            $key = $nb_labels + $key;
        }

        $label = $this->normalizeContent($label);

        if (($this->labels[$key] ?? null) === $label) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;
        ksort($labels);

        return new self(
            implode('.', array_reverse($labels)),
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
    }

    public function withoutLabel(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_labels = count($this->labels);
        foreach ($keys as &$offset) {
            if (- $nb_labels > $offset || $nb_labels - 1 < $offset) {
                throw InvalidDomainName::dueToInvalidLabelKey($key);
            }

            if (0 > $offset) {
                $offset += $nb_labels;
            }
        }
        unset($offset);

        $deleted_keys = array_keys(array_count_values($keys));
        $labels = [];
        foreach ($this->labels as $offset => $label) {
            if (!in_array($offset, $deleted_keys, true)) {
                $labels[] = $label;
            }
        }

        if ([] === $labels) {
            return new self(null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        $domain = implode('.', array_reverse($labels));

        return new self($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->asciiIDNAOption) {
            return $this;
        }

        return new self($this->domain, $option, $this->unicodeIDNAOption);
    }

    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->unicodeIDNAOption) {
            return $this;
        }

        return new self($this->domain, $this->asciiIDNAOption, $option);
    }
}
