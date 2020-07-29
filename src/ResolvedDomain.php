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
use function array_slice;
use function count;
use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function strlen;
use function substr;

final class ResolvedDomain implements ResolvedHostInterface
{
    private const REGEXP_IDN_PATTERN = '/[^\x20-\x7f]/';

    private DomainInterface $domain;

    private PublicSuffixInterface $publicSuffix;

    private DomainInterface $registrableDomain;

    private DomainInterface $subDomain;

    public function __construct(HostInterface $domain, ?PublicSuffixInterface $publicSuffix = null)
    {
        $this->domain = $this->setDomain($domain);
        $this->publicSuffix = $this->setPublicSuffix($publicSuffix);
        $this->registrableDomain = $this->setRegistrableDomain();
        $this->subDomain = $this->setSubDomain();
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['domain'], $properties['publicSuffix']);
    }

    /**
     * Sets the domain.
     */
    private function setDomain(HostInterface $domain): DomainInterface
    {
        if ($domain instanceof DomainInterface) {
            return $domain;
        }

        return new Domain($domain, $domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption());
    }

    /**
     * Sets the public suffix domain part.
     *
     * @throws UnableToResolveDomain If the public suffic can not be attached to the domain
     */
    private function setPublicSuffix(PublicSuffixInterface $publicSuffix = null): PublicSuffixInterface
    {
        $asciiIDNAOptions = $this->domain->getAsciiIDNAOption();
        $unicodeIDNAOptions = $this->domain->getUnicodeIDNAOption();

        if (null === $publicSuffix) {
            return PublicSuffix::fromNull($asciiIDNAOptions, $unicodeIDNAOptions);
        }

        if (null === $publicSuffix->getContent()) {
            return $publicSuffix
                ->withAsciiIDNAOption($asciiIDNAOptions)
                ->withUnicodeIDNAOption($unicodeIDNAOptions);
        }

        $domainContent = $this->domain->getContent();

        if (null === $domainContent) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if (2 > count($this->domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        if ('.' === substr($domainContent, -1, 1)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($this->domain);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        /** @var string $psContent */
        $psContent = $publicSuffix->getContent();
        if ($this->domain->getContent() === $psContent) {
            throw new UnableToResolveDomain(sprintf('The public suffix `%s` can not be equal to the domain name `%s`', $psContent, $this->domain));
        }

        /** @var string $domainName */
        $domainName = $this->domain->getContent();
        if ('.'.$psContent !== substr($domainName, - strlen($psContent) - 1)) {
            throw new UnableToResolveDomain(sprintf('The public suffix `%s` can not be assign to the domain name `%s`', $psContent, $this->domain));
        }

        return $publicSuffix;
    }

    /**
     * Normalize the domain name encoding content.
     */
    private function normalize(PublicSuffixInterface $subject): PublicSuffixInterface
    {
        if (1 !== preg_match(self::REGEXP_IDN_PATTERN, $this->domain->__toString())) {
            return $subject->toAscii();
        }

        return $subject->toUnicode();
    }

    /**
     * Computes the registrable domain part.
     */
    private function setRegistrableDomain(): DomainInterface
    {
        if (null === $this->publicSuffix->getContent()) {
            return Domain::fromNull($this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption());
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->__toString()),
            count($this->domain) - count($this->publicSuffix) - 1
        ));

        return new Domain($domain, $this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption());
    }

    /**
     * Computes the sub domain part.
     */
    private function setSubDomain(): DomainInterface
    {
        $asciiIDNAOptions = $this->domain->getAsciiIDNAOption();
        $unicodeIDNAOptions = $this->domain->getUnicodeIDNAOption();

        if (null === $this->registrableDomain->getContent()) {
            return Domain::fromNull($asciiIDNAOptions, $unicodeIDNAOptions);
        }

        $nbLabels = count($this->domain);
        $nbRegistrableLabels = count($this->publicSuffix) + 1;
        if ($nbLabels === $nbRegistrableLabels) {
            return Domain::fromNull($asciiIDNAOptions, $unicodeIDNAOptions);
        }

        $domain = implode('.', array_slice(
            explode('.', $this->domain->__toString()),
            0,
            $nbLabels - $nbRegistrableLabels
        ));

        return new Domain($domain, $asciiIDNAOptions, $unicodeIDNAOptions);
    }

    public function count(): int
    {
        return count($this->domain);
    }

    public function getHost(): DomainInterface
    {
        return $this->domain;
    }

    public function jsonSerialize(): ?string
    {
        return $this->domain->getContent();
    }

    public function getContent(): ?string
    {
        return $this->domain->getContent();
    }

    public function __toString(): string
    {
        return $this->domain->__toString();
    }

    public function getAsciiIDNAOption(): int
    {
        return $this->domain->getAsciiIDNAOption();
    }

    public function getUnicodeIDNAOption(): int
    {
        return $this->domain->getUnicodeIDNAOption();
    }

    public function getRegistrableDomain(): DomainInterface
    {
        return $this->registrableDomain;
    }

    public function getSubDomain(): DomainInterface
    {
        return $this->subDomain;
    }

    public function getPublicSuffix(): PublicSuffixInterface
    {
        return $this->publicSuffix;
    }

    public function toAscii(): self
    {
        return new self($this->domain->toAscii(), $this->publicSuffix->toAscii());
    }

    public function toUnicode(): self
    {
        return new self($this->domain->toUnicode(), $this->publicSuffix->toUnicode());
    }

    public function resolve($publicSuffix): self
    {
        if (!$publicSuffix instanceof PublicSuffixInterface) {
            $publicSuffix = PublicSuffix::fromUnknownSection($publicSuffix);
        }

        return new self($this->domain, $publicSuffix);
    }

    /**
     * @param mixed $publicSuffix a public suffix
     */
    public function withPublicSuffix($publicSuffix): self
    {
        if (!$publicSuffix instanceof PublicSuffixInterface) {
            $publicSuffix = PublicSuffix::fromUnknownSection($publicSuffix);
        }

        $publicSuffix = $this->normalize($publicSuffix);
        if ($this->publicSuffix == $publicSuffix) {
            return $this;
        }

        $domain = implode('.', array_reverse(array_slice($this->domain->labels(), count($this->publicSuffix))));
        if (null === $publicSuffix->getContent()) {
            return new self(
                new Domain($domain, $this->domain->getAsciiIDNAOption(), $this->domain->getUnicodeIDNAOption()),
                null
            );
        }

        /** @var DomainInterface $domain */
        $domain = new Domain(
            $domain.'.'.$publicSuffix->getContent(),
            $this->domain->getAsciiIDNAOption(),
            $this->domain->getUnicodeIDNAOption()
        );

        /** @var PublicSuffixInterface $publicSuffix */
        $publicSuffix = $publicSuffix
            ->withAsciiIDNAOption($this->domain->getAsciiIDNAOption())
            ->withUnicodeIDNAOption($this->domain->getUnicodeIDNAOption());

        return new self($domain, $publicSuffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withSubDomain($subDomain): self
    {
        if (null === $this->registrableDomain->getContent()) {
            throw UnableToResolveDomain::dueToMissingRegistrableDomain($this);
        }

        if (null === $subDomain) {
            $subDomain = Domain::fromNull($this->getAsciiIDNAOption(), $this->getUnicodeIDNAOption());
        }

        if (!$subDomain instanceof DomainInterface) {
            $subDomain = new Domain($subDomain);
        }

        $subDomain = $subDomain ?? Domain::fromNull($this->getAsciiIDNAOption(), $this->getUnicodeIDNAOption());
        if ($this->subDomain == $subDomain) {
            return $this;
        }

        /** @var DomainInterface $subDomain */
        $subDomain = $subDomain->toAscii();
        if (1 === preg_match(self::REGEXP_IDN_PATTERN, (string) $this->domain->getContent())) {
            /** @var DomainInterface $subDomain */
            $subDomain = $subDomain->toUnicode();
        }

        $domain = new Domain(
            $subDomain.'.'.$this->registrableDomain,
            $this->getAsciiIDNAOption(),
            $this->getUnicodeIDNAOption()
        );

        return new self($domain, $this->publicSuffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withAsciiIDNAOption(int $option): self
    {
        if ($option === $this->domain->getAsciiIDNAOption()) {
            return $this;
        }

        /** @var DomainInterface $asciiDomain */
        $asciiDomain = $this->domain->withAsciiIDNAOption($option);

        /** @var PublicSuffixInterface $asciiPublicSuffix */
        $asciiPublicSuffix = $this->publicSuffix->withAsciiIDNAOption($option);

        return new self($asciiDomain, $asciiPublicSuffix);
    }

    /**
     * {@inheritDoc}
     */
    public function withUnicodeIDNAOption(int $option): self
    {
        if ($option === $this->domain->getUnicodeIDNAOption()) {
            return $this;
        }

        /** @var DomainInterface $unicodeDomain */
        $unicodeDomain = $this->domain->withUnicodeIDNAOption($option);

        /** @var PublicSuffixInterface $unicodePublicSuffix */
        $unicodePublicSuffix = $this->publicSuffix->withUnicodeIDNAOption($option);

        return new self($unicodeDomain, $unicodePublicSuffix);
    }
}
