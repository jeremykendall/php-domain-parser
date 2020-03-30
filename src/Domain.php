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
use Pdp\Exception\CouldNotResolveSubDomain;
use Pdp\Exception\InvalidLabel;
use Pdp\Exception\InvalidLabelKey;
use TypeError;
use function array_count_values;
use function array_keys;
use function array_reverse;
use function array_slice;
use function array_unshift;
use function count;
use function explode;
use function gettype;
use function implode;
use function in_array;
use function ksort;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function substr;
use const IDNA_DEFAULT;

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

    private const REGEXP_IDN_PATTERN = '/[^\x20-\x7f]/';

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
     * New instance.
     * @param null|mixed        $domain
     * @param null|PublicSuffix $publicSuffix
     * @param int               $asciiIDNAOption
     * @param int               $unicodeIDNAOption
     */
    public function __construct(
        $domain = null,
        PublicSuffix $publicSuffix = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ) {
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
        $infos = $this->parse($domain, $asciiIDNAOption, $unicodeIDNAOption);
        $this->labels = $infos['labels'];
        $this->isTransitionalDifferent = $infos['isTransitionalDifferent'];
        if ([] !== $this->labels) {
            $this->domain = implode('.', array_reverse($this->labels));
        }
        $this->publicSuffix = $this->setPublicSuffix(
            $publicSuffix ?? new PublicSuffix(null, '', $asciiIDNAOption, $unicodeIDNAOption)
        );
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['domain'],
            $properties['publicSuffix'],
            $properties['asciiIDNAOption'] ?? IDNA_DEFAULT,
            $properties['unicodeIDNAOption'] ?? IDNA_DEFAULT
        );
    }

    /**
     * Sets the public suffix domain part.
     *
     * @param PublicSuffix $publicSuffix
     *
     * @throws CouldNotResolvePublicSuffix If the public suffic can not be attached to the domain
     *
     * @return PublicSuffix
     */
    private function setPublicSuffix(PublicSuffix $publicSuffix): PublicSuffix
    {
        if (null === $publicSuffix->getContent()) {
            return $publicSuffix;
        }

        if (null === $this->domain || !$this->isResolvable()) {
            throw CouldNotResolvePublicSuffix::dueToUnresolvableDomain($this);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        /** @var string $psContent */
        $psContent = $publicSuffix->getContent();
        if ($this->domain === $psContent) {
            throw new CouldNotResolvePublicSuffix(sprintf('The public suffix `%s` can not be equal to the domain name `%s`', $psContent, $this->domain));
        }

        if ('.'.$psContent !== substr($this->domain, - strlen($psContent) - 1)) {
            throw new CouldNotResolvePublicSuffix(sprintf('The public suffix `%s` can not be assign to the domain name `%s`', $psContent, $this->domain));
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

        if (1 !== preg_match(self::REGEXP_IDN_PATTERN, $this->domain)) {
            /** @var PublicSuffix $result */
            $result = $subject->toAscii();

            return $result;
        }

        /** @var PublicSuffix $result */
        $result = $subject->toUnicode();

        return $result;
    }

    /**
     * Computes the registrable domain part.
     *
     * @return string|null
     */
    private function setRegistrableDomain()
    {
        if (null === $this->domain) {
            return null;
        }

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
        if (null === $this->domain) {
            return null;
        }

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
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo(): array
    {
        return  [
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
    public function count(): int
    {
        return count($this->labels);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->domain;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
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
     *
     * @return int
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
     *
     * @return int
     */
    public function getUnicodeIDNAOption(): int
    {
        return $this->unicodeIDNAOption;
    }

    /**
     * Returns true if domain contains deviation characters.
     *
     * @see http://unicode.org/reports/tr46/#Transition_Considerations
     *
     * @return bool
     */
    public function isTransitionalDifferent(): bool
    {
        return $this->isTransitionalDifferent;
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
    public function getRegistrableDomain(): ?string
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
    public function getSubDomain(): ?string
    {
        return $this->subDomain;
    }

    /**
     * Returns the public suffix.
     *
     * @return string|null
     */
    public function getPublicSuffix(): ?string
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
        return null !== $this->domain
            && '.' !== substr($this->domain, -1, 1)
            && 1 < count($this->labels)
        ;
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
    public function toAscii(): DomainInterface
    {
        if (null === $this->domain) {
            return $this;
        }

        $domain = $this->idnToAscii($this->domain, $this->asciiIDNAOption);
        if ($domain === $this->domain) {
            return $this;
        }

        return new self($domain, $this->publicSuffix, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode(): DomainInterface
    {
        if (null === $this->domain || false === strpos($this->domain, 'xn--')) {
            return $this;
        }

        return new self(
            $this->idnToUnicode($this->domain, $this->unicodeIDNAOption),
            $this->publicSuffix,
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
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
            $publicSuffix = new PublicSuffix(
                $publicSuffix,
                '',
                $this->asciiIDNAOption,
                $this->unicodeIDNAOption
            );
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        return new self($this->domain, $publicSuffix, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * Returns an instance with the specified public suffix added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new public suffix
     *
     * If the domain already has a public suffix it will be replaced by the new value
     * otherwise the public suffix content is added to or remove from the current domain.
     *
     * @param mixed $publicSuffix
     */
    public function withPublicSuffix($publicSuffix): self
    {
        if (!$publicSuffix instanceof PublicSuffix) {
            $publicSuffix = new PublicSuffix(
                $publicSuffix,
                '',
                $this->asciiIDNAOption,
                $this->unicodeIDNAOption
            );
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $domain = implode('.', array_reverse(array_slice($this->labels, count($this->publicSuffix))));
        if (null === $publicSuffix->getContent()) {
            return new self($domain, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        return new self(
            $domain.'.'.$publicSuffix->getContent(),
            $publicSuffix,
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
    }


    /**
     * Returns an instance with the specified sub domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new sub domain
     *
     * @param mixed $subDomain the subdomain to add
     *
     * @throws CouldNotResolveSubDomain If the Sub domain can not be added to the current Domain
     *
     * @return self
     */
    public function withSubDomain($subDomain): self
    {
        if (null === $this->registrableDomain) {
            throw new CouldNotResolveSubDomain('A subdomain can not be added to a domain without a registrable domain part.');
        }

        $subDomain = $this->normalizeContent($subDomain);
        if ($this->subDomain === $subDomain) {
            return $this;
        }

        if (null === $subDomain) {
            return new self(
                $this->registrableDomain,
                $this->publicSuffix,
                $this->asciiIDNAOption,
                $this->unicodeIDNAOption
            );
        }

        return new self(
            $subDomain.'.'.$this->registrableDomain,
            $this->publicSuffix,
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
        );
    }

    /**
     * Filter a subdomain to update the domain part.
     *
     * @param mixed $domain
     *
     * @throws TypeError if the domain can not be converted
     *
     * @return string|null
     */
    private function normalizeContent($domain)
    {
        if ($domain instanceof DomainInterface) {
            $domain = $domain->getContent();
        }

        if (null === $domain) {
            return $domain;
        }

        if (!is_scalar($domain) && !method_exists($domain, '__toString')) {
            throw new TypeError(sprintf('The domain or label must be a scalar, a stringable object or NULL, `%s` given', gettype($domain)));
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

    /**
     * Prepends a label to the domain.
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
     * Appends a label to the domain.
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
     * @throws InvalidLabelKey If the key is out of bounds
     * @throws InvalidLabel    If the label is converted to the NULL value
     *
     * @return self
     */
    public function withLabel(int $key, $label): self
    {
        $nb_labels = count($this->labels);
        if ($key < - $nb_labels - 1 || $key > $nb_labels) {
            throw new InvalidLabelKey(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $key) {
            $key = $nb_labels + $key;
        }

        $label = $this->normalizeContent($label);
        if (null === $label) {
            throw new InvalidLabel(sprintf('The label can not be NULL'));
        }

        if (($this->labels[$key] ?? null) === $label) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;
        ksort($labels);

        if (null !== $this->publicSuffix->getLabel($key)) {
            return new self(
                implode('.', array_reverse($labels)),
                null,
                $this->asciiIDNAOption,
                $this->unicodeIDNAOption
            );
        }

        return new self(
            implode('.', array_reverse($labels)),
            $this->publicSuffix,
            $this->asciiIDNAOption,
            $this->unicodeIDNAOption
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
     * @throws InvalidLabelKey If the key is out of bounds
     *
     * @return self
     */
    public function withoutLabel(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_labels = count($this->labels);
        foreach ($keys as &$offset) {
            if (- $nb_labels > $offset || $nb_labels - 1 < $offset) {
                throw new InvalidLabelKey(sprintf('the key `%s` is invalid', $offset));
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
            return new self(null, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        $domain = implode('.', array_reverse($labels));
        $psContent = $this->publicSuffix->getContent();
        if (null === $psContent || '.'.$psContent !== substr($domain, - strlen($psContent) - 1)) {
            return new self($domain, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        return new self($domain, $this->publicSuffix, $this->asciiIDNAOption, $this->unicodeIDNAOption);
    }

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     *
     * @param int $option
     *
     * @return self
     */
    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->asciiIDNAOption) {
            return $this;
        }

        return new self($this->domain, $this->publicSuffix, $option, $this->unicodeIDNAOption);
    }

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     *
     * @param int $option
     *
     * @return self
     */
    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->unicodeIDNAOption) {
            return $this;
        }

        return new self($this->domain, $this->publicSuffix, $this->asciiIDNAOption, $option);
    }
}
