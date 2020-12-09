<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use JsonException;
use SplTempFileObject;
use TypeError;
use function count;
use function fclose;
use function fopen;
use function gettype;
use function in_array;
use function is_object;
use function is_string;
use function json_decode;
use function method_exists;
use function preg_match;
use function stream_get_contents;
use function strpos;
use function trim;
use const JSON_THROW_ON_ERROR;

final class TopLevelDomains implements RootZoneDatabase
{
    private const IANA_DATE_FORMAT = 'D M d H:i:s Y e';

    private const REGEXP_HEADER_LINE = '/^\# Version (?<version>\d+), Last Updated (?<date>.*?)$/';

    private DateTimeImmutable $lastUpdated;

    private string $version;

    /**
     * @var array<string>
     */
    private array $records;

    /**
     * @param array<string> $records
     */
    private function __construct(array $records, string $version, DateTimeImmutable $lastUpdated)
    {
        $this->records = $records;
        $this->version = $version;
        $this->lastUpdated = $lastUpdated;
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
        if (is_object($content) && method_exists($content, '__toString')) {
            $content = (string) $content;
        }

        if (!is_string($content)) {
            throw new TypeError('The content to be converted should be a string or a Stringable object, `'.gettype($content).'` given.');
        }

        $data = self::parse($content);

        /** @var DateTimeImmutable $lastUpdated */
        $lastUpdated = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['lastUpdated']);

        return new self($data['records'], $data['version'], $lastUpdated);
    }

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @throws UnableToLoadRootZoneDatabase if the content is invalid or can not be correctly parsed and converted
     *
     * @return array{version:string, lastUpdated:string, records:array<string>}
     */
    public static function parse(string $content): array
    {
        $data = [];
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        /** @var string $line */
        foreach ($file as $line) {
            $line = trim($line);
            if ([] === $data) {
                $data = self::extractHeader($line);
                continue;
            }

            if (false === strpos($line, '#')) {
                $data['records'] = $data['records'] ?? [];
                $data['records'][] = self::extractRootZone($line);
                continue;
            }

            throw UnableToLoadRootZoneDatabase::dueToInvalidLine($line);
        }

        if (isset($data['version'], $data['lastUpdated'], $data['records'])) {
            return $data;
        }

        throw UnableToLoadRootZoneDatabase::dueToFailedConversion();
    }

    /**
     * Extract IANA Root Zone Database header info.
     *
     * @throws UnableToLoadRootZoneDatabase if the Header line is invalid
     *
     * @return array{version:string, lastUpdated:string}
     */
    private static function extractHeader(string $content): array
    {
        if (1 !== preg_match(self::REGEXP_HEADER_LINE, $content, $matches)) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidVersionLine($content);
        }

        /** @var DateTimeImmutable $date */
        $date = DateTimeImmutable::createFromFormat(self::IANA_DATE_FORMAT, $matches['date']);

        return [
            'version' => $matches['version'],
            'lastUpdated' => $date->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Extract IANA Root Zone.
     *
     * @throws UnableToLoadRootZoneDatabase If the Root Zone is invalid
     */
    private static function extractRootZone(string $content): string
    {
        try {
            $tld = Suffix::fromUnknown(Domain::fromIDNA2008($content))->toAscii();
        } catch (CannotProcessHost $exception) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content, $exception);
        }

        if (1 !== $tld->count() || '' === $tld->value()) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content);
        }

        return $tld->toString();
    }

    public static function fromJsonString(string $jsonString): self
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidJson($exception);
        }

        if (!isset($data['records'], $data['version'], $data['lastUpdated'])) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidHashMap();
        }

        /** @var DateTimeImmutable $lastUpdated */
        $lastUpdated = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['lastUpdated']);

        return new self($data['records'], $data['version'], $lastUpdated);
    }

    /**
     * @param array{records:array<string>, version:string, lastUpdated:DateTimeImmutable} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['records'], $properties['version'], $properties['lastUpdated']);
    }

    public function version(): string
    {
        return $this->version;
    }

    public function lastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return [] === $this->records;
    }

    /**
     * @return Iterator<EffectiveTopLevelDomain>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->records as $tld) {
            yield Suffix::fromIANA(Domain::fromIDNA2008($tld)->toAscii());
        }
    }

    /**
     * @return array{version:string, records:array<string>, lastUpdated:string}
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'records' => $this->records,
            'lastUpdated' => $this->lastUpdated->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function resolve($host): ResolvedDomainName
    {
        try {
            $domain = $this->validateDomain($host);
            if ($this->containsTopLevelDomain($domain)) {
                return ResolvedDomain::fromIANA($domain);
            }
            return ResolvedDomain::fromNull($domain);
        } catch (UnableToResolveDomain $exception) {
            return ResolvedDomain::fromNull($exception->getDomain());
        } catch (SyntaxError $exception) {
            return ResolvedDomain::fromNull(Domain::fromIDNA2008(null));
        }
    }

    /**
     * Assert the domain is valid and is resolvable.
     *
     * @param mixed $domain a type that supports instantiating a Domain from.
     *
     * @throws SyntaxError           If the domain is invalid
     * @throws UnableToResolveDomain If the domain can not be resolved
     */
    private function validateDomain($domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!($domain instanceof DomainName)) {
            $domain = Domain::fromIDNA2008($domain);
        }

        return $domain;
    }

    private function containsTopLevelDomain(DomainName $domain): bool
    {
        $label = $domain->toAscii()->label(0);
        if (in_array($label, [null, ''], true)) {
            return false;
        }

        foreach ($this as $tld) {
            if ($tld->value() === $label) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function getIANADomain($domain): ResolvedDomainName
    {
        $domain = $this->validateDomain($domain);
        if (!$this->containsTopLevelDomain($domain)) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'IANA');
        }

        return ResolvedDomain::fromIANA($domain);
    }
}
