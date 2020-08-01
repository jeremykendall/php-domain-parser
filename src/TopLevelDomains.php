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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use function count;
use function fclose;
use function fopen;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function stream_get_contents;
use function substr;
use const DATE_ATOM;
use const IDNA_DEFAULT;
use const JSON_ERROR_NONE;

final class TopLevelDomains implements RootZoneDatabase
{
    private DateTimeImmutable $modifiedDate;

    private string $version;

    private array $records;

    private int $asciiIDNAOption;

    private int $unicodeIDNAOption;

    private function __construct(
        array $records,
        string $version,
        DateTimeInterface $modifiedDate,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ) {
        if ($modifiedDate instanceof DateTime) {
            $modifiedDate = DateTimeImmutable::createFromMutable($modifiedDate);
        }

        $this->records = $records;
        $this->version = $version;
        $this->modifiedDate = $modifiedDate;
        $this->asciiIDNAOption = $asciiIDNAOption;
        $this->unicodeIDNAOption = $unicodeIDNAOption;
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadRootZoneDatabase If the rules can not be loaded from the path
     */
    public static function fromPath(
        string $path,
        $context = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): RootZoneDatabase {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        $resource = @fopen(...$args);
        if (false === $resource) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidPath($path);
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return self::fromString($content, $asciiIDNAOption, $unicodeIDNAOption);
    }

    public static function fromString(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): RootZoneDatabase {
        static $converter;

        $converter = $converter ?? new RootZoneDatabaseConverter();

        $data = $converter->convert($content);
        /** @var DateTimeImmutable $modifiedDate */
        $modifiedDate = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['modifiedDate']);

        return new self(
            $data['records'],
            $data['version'],
            $modifiedDate,
            $asciiIDNAOption,
            $unicodeIDNAOption
        );
    }

    public static function fromJsonString(
        string $jsonString,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): RootZoneDatabase {
        $data = json_decode($jsonString, true);
        $errorCode = json_last_error();
        if (JSON_ERROR_NONE !== $errorCode) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidJson($errorCode, json_last_error_msg());
        }

        if (!isset($data['records'], $data['version'], $data['modifiedDate'])) {
            throw  UnableToLoadRootZoneDatabase::dueToInvalidHashMap();
        }

        /** @var DateTimeImmutable $modifiedDate */
        $modifiedDate = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['modifiedDate']);

        return new self($data['records'], $data['version'], $modifiedDate, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): RootZoneDatabase
    {
        return new self(
            $properties['records'],
            $properties['version'],
            $properties['modifiedDate'],
            $properties['asciiIDNAOption'],
            $properties['unicodeIDNAOption']
        );
    }

    /**
     * Returns the Version ID.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns the List Last Modified Date.
     */
    public function getModifiedDate(): DateTimeImmutable
    {
        return $this->modifiedDate;
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
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->records);
    }

    /**
     * Tells whether the list is empty.
     */
    public function isEmpty(): bool
    {
        return [] === $this->records;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        foreach ($this->records as $tld) {
            yield PublicSuffix::fromICANNSection($tld, $this->asciiIDNAOption, $this->unicodeIDNAOption)->toAscii();
        }
    }

    /**
     * Returns an array representation of the list.
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'records' => $this->records,
            'modifiedDate' => $this->modifiedDate->format(DATE_ATOM),
        ];
    }

    /**
     * Tells whether the submitted TLD is a valid Top Level Domain.
     *
     * @param mixed $tld a TLD in a type that can be converted into a DomainInterface instance
     */
    public function contains($tld): bool
    {
        try {
            if (!$tld instanceof DomainName) {
                $tld = new Domain($tld, $this->asciiIDNAOption, $this->unicodeIDNAOption);
            }
        } catch (ExceptionInterface $exception) {
            return false;
        }

        if (1 !== count($tld)) {
            return false;
        }

        /** @var DomainName $asciiDomain */
        $asciiDomain = $tld->toAscii();
        $label = $asciiDomain->label(0);
        foreach ($this as $knownTld) {
            if ($knownTld->getContent() === $label) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a domain where its public suffix is the found TLD.
     *
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function resolve($domain): ResolvedHost
    {
        if ($domain instanceof ResolvedHost) {
            $domain = $domain->getHost();
            $domain
                ->withUnicodeIDNAOption($this->unicodeIDNAOption)
                ->withAsciiIDNAOption($this->asciiIDNAOption);
        }

        if (!$domain instanceof DomainName) {
            $domain = new Domain($domain, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        $domainContent = $domain->getContent();
        if (null === $domainContent) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        if (2 > count($domain)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        if ('.' === substr($domainContent, -1, 1)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        /** @var DomainName $asciiDomain */
        $asciiDomain = $domain->toAscii();

        $publicSuffix = null;
        $label = $asciiDomain->label(0);
        foreach ($this as $tld) {
            if ($tld->getContent() === $label) {
                $publicSuffix = $tld;
                break;
            }
        }

        return new ResolvedDomain($domain, PublicSuffix::fromUnknownSection($publicSuffix));
    }

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withAsciiIDNAOption(int $option): RootZoneDatabase
    {
        if ($option === $this->asciiIDNAOption) {
            return $this;
        }

        $clone = clone $this;
        $clone->asciiIDNAOption = $option;

        return $clone;
    }

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withUnicodeIDNAOption(int $option): RootZoneDatabase
    {
        if ($option === $this->unicodeIDNAOption) {
            return $this;
        }

        $clone = clone $this;
        $clone->unicodeIDNAOption = $option;

        return $clone;
    }
}
