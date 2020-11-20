<?php

declare(strict_types=1);

namespace Pdp;

use TypeError;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_unshift;
use function count;
use function explode;
use function filter_var;
use function gettype;
use function implode;
use function in_array;
use function is_object;
use function is_scalar;
use function ksort;
use function method_exists;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function strtolower;
use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

final class Domain implements DomainName
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
    private function __construct($domain, int $asciiIDNAOption, int $unicodeIDNAOption)
    {
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

    /**
     * @param null|mixed $domain
     */
    public static function fromIDNA2003($domain): self
    {
        return new self($domain, IDNA_DEFAULT, IDNA_DEFAULT);
    }

    /**
     * @param null|mixed $domain
     */
    public static function fromIDNA2008($domain): self
    {
        return new self($domain, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
    }

    /**
     * Parse and format the domain to ensure it is valid.
     * Returns an array containing the formatted domain name labels
     * and the domain transitional information.
     *
     * For example: parse('wWw.uLb.Ac.be') should return ['be', 'ac', 'ulb', 'www'];.
     *
     * @param mixed $domain a domain
     *
     *@throws SyntaxError If the host is not a domain
     *@throws SyntaxError If the domain is not a host
     *
     *@return array<string>
     */
    private function parse($domain, int $asciiOption, int $unicodeOption): array
    {
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->domain()->value();
        }

        if ($domain instanceof Host) {
            $domain = $domain->value();
        }

        if (null === $domain) {
            return [];
        }

        if (is_object($domain) && method_exists($domain, '__toString')) {
            $domain = (string) $domain;
        }

        if (!is_scalar($domain)) {
            throw new TypeError(sprintf('The domain must be a string, a stringable object, a Host object or NULL; `%s` given', gettype($domain)));
        }

        $domain = (string) $domain;
        if ('' === $domain) {
            return [''];
        }

        $res = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (false !== $res) {
            throw SyntaxError::dueToUnsupportedType($domain);
        }

        $formattedDomain = rawurldecode($domain);

        // Note that unreserved is purposely missing . as it is used to separate labels.
        static $domainName = '/(?(DEFINE)
                (?<unreserved>[a-z0-9_~\-])
                (?<sub_delims>[!$&\'()*+,;=])
                (?<encoded>%[A-F0-9]{2})
                (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded)){1,63})
            )
            ^(?:(?&reg_name)\.){0,126}(?&reg_name)\.?$/ix';
        if (1 === preg_match($domainName, $formattedDomain)) {
            return array_reverse(explode('.', strtolower($formattedDomain)));
        }

        // a domain name can not contains URI delimiters or space
        static $genDelimiters = '/[:\/?#\[\]@ ]/';
        if (1 === preg_match($genDelimiters, $formattedDomain)) {
            throw SyntaxError::dueToInvalidCharacters($domain);
        }

        // if the domain name does not contains UTF-8 chars then it is malformed
        if (1 === preg_match(self::REGEXP_IDN_PATTERN, $formattedDomain)) {
            $asciiDomain = IntlIdna::toAscii($domain, $asciiOption);

            /** @var array $labels */
            $labels = array_reverse(explode('.', IntlIdna::toUnicode($asciiDomain, $unicodeOption)));

            return $labels;
        }

        throw SyntaxError::dueToInvalidCharacters($domain);
    }

    public function getIterator()
    {
        foreach ($this->labels as $offset => $label) {
            yield $label;
        }
    }

    public function isIdna2008(): bool
    {
        return IDNA_NONTRANSITIONAL_TO_ASCII === $this->asciiIDNAOption;
    }

    public function isAscii(): bool
    {
        return null === $this->domain ||
            1 !== preg_match(self::REGEXP_IDN_PATTERN, $this->domain);
    }

    public function jsonSerialize(): ?string
    {
        return $this->value();
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function value(): ?string
    {
        return $this->domain;
    }

    public function toString(): string
    {
        return (string) $this->value();
    }

    public function label(int $key): ?string
    {
        if ($key < 0) {
            $key += count($this->labels);
        }

        return $this->labels[$key] ?? null;
    }

    public function keys(string $label = null): array
    {
        $args = (null !== $label) ? [$label, true] : [];

        return array_keys($this->labels, ...$args);
    }

    public function labels(): array
    {
        return $this->labels;
    }

    public function toAscii(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = IntlIdna::toAscii($this->domain, $this->asciiIDNAOption);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    public function toUnicode(): self
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = IntlIdna::toUnicode($this->domain, $this->unicodeIDNAOption);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * Filter a subdomain to update the domain part.
     *
     * @param string|object $domain a domain
     *
     * @throws TypeError if the domain can not be converted
     */
    private function normalize($domain): string
    {
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->domain();
        }

        if ($domain instanceof Host) {
            $domain = $domain->value();
        }

        if (null === $domain || (!is_string($domain) && !method_exists($domain, '__toString'))) {
            throw new TypeError(sprintf('The label must be a '.Host::class.', a stringable object or a string, `%s` given', gettype($domain)));
        }

        $domain = (string) $domain;
        if (null === $this->domain) {
            return $domain;
        }

        if (1 === preg_match(self::REGEXP_IDN_PATTERN, $this->domain)) {
            return IntlIdna::toUnicode($domain, $this->unicodeIDNAOption);
        }

        return IntlIdna::toAscii($domain, $this->asciiIDNAOption);
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
            throw SyntaxError::dueToInvalidLabelKey($this, $key);
        }

        if (0 > $key) {
            $key = $nb_labels + $key;
        }

        $label = $this->normalize($label);

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
                throw SyntaxError::dueToInvalidLabelKey($this, $key);
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
}
