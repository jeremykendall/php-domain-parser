<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use function count;
use function fclose;
use function fopen;
use function json_decode;
use function stream_get_contents;
use function substr;
use const JSON_THROW_ON_ERROR;

final class TopLevelDomains implements RootZoneDatabase
{
    private DateTimeImmutable $lastUpdated;

    private string $version;

    private array $records;

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
        static $converter;

        $converter = $converter ?? new RootZoneDatabaseConverter();

        $data = $converter->convert($content);
        /** @var DateTimeImmutable $lastUpdated */
        $lastUpdated = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['lastUpdated']);

        return new self($data['records'], $data['version'], $lastUpdated);
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

    public static function __set_state(array $properties): RootZoneDatabase
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

    public function getIterator()
    {
        foreach ($this->records as $tld) {
            yield PublicSuffix::fromUnknown(Domain::fromIDNA2008($tld)->toAscii());
        }
    }

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
            return $this->getTopLevelDomain($host);
        } catch (UnableToResolveDomain $exception) {
            $domain = $exception->fetchDomain();
            if (null !== $domain) {
                return new ResolvedDomain($domain);
            }

            return new ResolvedDomain(Domain::fromIDNA2008($host));
        } catch (SyntaxError $exception) {
            return new ResolvedDomain(Domain::fromIDNA2008(null));
        }
    }

    /**
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function getTopLevelDomain($domain): ResolvedDomainName
    {
        if ($domain instanceof ExternalDomainName) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = Domain::fromIDNA2008($domain);
        }

        if ((2 > count($domain)) || ('.' === substr($domain->toString(), -1, 1))) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        $label = $domain->toAscii()->label(0);
        foreach ($this as $tld) {
            if ($tld->value() === $label) {
                $publicSuffix = $domain->isIdna2008() ? Domain::fromIDNA2008($tld) : Domain::fromIDNA2003($tld);

                return new ResolvedDomain($domain, PublicSuffix::fromIANA($publicSuffix));
            }
        }

        $publicSuffix = $domain->isIdna2008() ? Domain::fromIDNA2008(null) : Domain::fromIDNA2003(null);

        return new ResolvedDomain($domain, PublicSuffix::fromUnknown($publicSuffix));
    }
}
