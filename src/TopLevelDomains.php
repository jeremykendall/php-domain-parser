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

use Countable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use IteratorAggregate;
use Pdp\Exception\CouldNotLoadTLDs;
use function count;
use function fclose;
use function fopen;
use function stream_get_contents;
use const DATE_ATOM;
use const IDNA_DEFAULT;

/**
 * A class to resolve domain name against the IANA Root Database.
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class TopLevelDomains implements Countable, IteratorAggregate
{
    /**
     * @var DateTimeImmutable
     */
    private $modifiedDate;

    /**
     * @var string
     */
    private $version;

    /**
     * @var array
     */
    private $records;

    /**
     * @var int
     */
    private $asciiIDNAOption;

    /**
     * @var int
     */
    private $unicodeIDNAOption;

    /**
     * New instance.
     *
     * @internal
     *
     * @param array             $records
     * @param string            $version
     * @param DateTimeInterface $modifiedDate
     * @param int               $asciiIDNAOption
     * @param int               $unicodeIDNAOption
     */
    public function __construct(
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
     * @param string        $path
     * @param null|resource $context
     * @param int           $asciiIDNAOption
     * @param int           $unicodeIDNAOption
     *
     * @throws CouldNotLoadTLDs If the rules can not be loaded from the path
     *
     * @return self
     */
    public static function createFromPath(
        string $path,
        $context = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): self {
        $args = [$path, 'r', false];
        if (null !== $context) {
            $args[] = $context;
        }

        $resource = @fopen(...$args);
        if (false === $resource) {
            throw new CouldNotLoadTLDs(sprintf('`%s`: failed to open stream: No such file or directory', $path));
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return self::createFromString($content, $asciiIDNAOption, $unicodeIDNAOption);
    }

    /**
     * Returns a new instance from a string.
     *
     * @param string $content
     * @param int    $asciiIDNAOption
     * @param int    $unicodeIDNAOption
     *
     * @return self
     */
    public static function createFromString(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): self {
        static $converter;

        $converter = $converter ?? new TLDConverter();

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

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['records'],
            $properties['version'],
            $properties['modifiedDate'],
            $properties['asciiIDNAOption'] ?? IDNA_DEFAULT,
            $properties['unicodeIDNAOption'] ?? IDNA_DEFAULT
        );
    }

    /**
     * Returns the Version ID.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns the List Last Modified Date.
     *
     * @return DateTimeImmutable
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
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->records);
    }

    /**
     * Tells whether the list is empty.
     *
     * @return bool
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
            yield (new PublicSuffix(
                $tld,
                PublicSuffix::ICANN_DOMAINS,
                $this->asciiIDNAOption,
                $this->unicodeIDNAOption
            ))->toAscii();
        }
    }

    /**
     * Returns an array representation of the list.
     *
     * @return array
     */
    public function toArray(): array
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
     * @param mixed $tld
     *
     * @return bool
     */
    public function contains($tld): bool
    {
        try {
            if (!$tld instanceof Domain) {
                $tld = new Domain($tld, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
            }
        } catch (Exception $e) {
            return false;
        }

        if (1 !== count($tld)) {
            return false;
        }

        $label = $tld->toAscii()->getLabel(0);
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
     * @param mixed $domain
     *
     * @return Domain
     */
    public function resolve($domain): Domain
    {
        try {
            if (!$domain instanceof Domain) {
                $domain = new Domain($domain, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
            }
        } catch (Exception $e) {
            return new Domain(null, null, $this->asciiIDNAOption, $this->unicodeIDNAOption);
        }

        if (!$domain->isResolvable()) {
            return $domain;
        }

        $publicSuffix = null;
        $label = $domain->toAscii()->getLabel(0);
        foreach ($this as $tld) {
            if ($tld->getContent() === $label) {
                $publicSuffix = $tld;
                break;
            }
        }

        return $domain->resolve($publicSuffix);
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

        $clone = clone $this;
        $clone->unicodeIDNAOption = $option;

        return $clone;
    }
}
