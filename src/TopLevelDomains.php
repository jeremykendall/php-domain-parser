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
use const JSON_ERROR_NONE;

final class TopLevelDomains implements RootZoneDatabase
{
    private DateTimeImmutable $modifiedDate;

    private string $version;

    private array $records;

    private function __construct(array $records, string $version, DateTimeInterface $modifiedDate)
    {
        if ($modifiedDate instanceof DateTime) {
            $modifiedDate = DateTimeImmutable::createFromMutable($modifiedDate);
        }

        $this->records = $records;
        $this->version = $version;
        $this->modifiedDate = $modifiedDate;
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadRootZoneDatabase If the rules can not be loaded from the path
     */
    public static function fromPath(string $path, $context = null): self
    {
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

        return self::fromString($content);
    }

    /**
     * Returns a new instance from a string.
     *
     * @param object|string $content a string or an object which exposes the __toString method
     */
    public static function fromString($content): self
    {
        static $converter;

        $converter = $converter ?? new RootZoneDatabaseConverter();

        $data = $converter->convert($content);
        /** @var DateTimeImmutable $modifiedDate */
        $modifiedDate = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['modifiedDate']);

        return new self($data['records'], $data['version'], $modifiedDate);
    }

    public static function fromJsonString(string $jsonString): self
    {
        $data = json_decode($jsonString, true);
        $errorCode = json_last_error();
        if (JSON_ERROR_NONE !== $errorCode) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidJson($errorCode, json_last_error_msg());
        }

        if (!isset($data['records'], $data['version'], $data['modifiedDate'])) {
            throw  UnableToLoadRootZoneDatabase::dueToInvalidHashMap();
        }

        /** @var DateTimeImmutable $modifiedDate */
        $modifiedDate = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['modifiedDate']);

        return new self($data['records'], $data['version'], $modifiedDate);
    }

    public static function __set_state(array $properties): RootZoneDatabase
    {
        return new self($properties['records'], $properties['version'], $properties['modifiedDate']);
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getModifiedDate(): DateTimeImmutable
    {
        return $this->modifiedDate;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return [] === $this->records;
    }

    public function getIterator()
    {
        foreach ($this->records as $tld) {
            yield PublicSuffix::fromUnknown($tld)->toAscii();
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'records' => $this->records,
            'modifiedDate' => $this->modifiedDate->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param mixed $tld a TLD in a type that can be converted into a DomainInterface instance
     */
    public function contains($tld): bool
    {
        try {
            if (!$tld instanceof DomainName) {
                $tld = new Domain($tld);
            }
        } catch (ExceptionInterface $exception) {
            return false;
        }

        if (1 !== count($tld)) {
            return false;
        }

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
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function resolve($domain): ResolvedDomainName
    {
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->getDomain();
        }

        if (!$domain instanceof DomainName) {
            $domain = new Domain($domain);
        }

        if ((2 > count($domain)) || ('.' === substr((string) $domain, -1, 1))) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        $label = $domain->toAscii()->label(0);
        foreach ($this as $tld) {
            if ($tld->getContent() === $label) {
                return new ResolvedDomain($domain, PublicSuffix::fromUnknown($tld));
            }
        }

        return new ResolvedDomain($domain, PublicSuffix::fromNull($domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()));
    }
}
