<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeInterface;
use Iterator;
use JsonException;
use function count;
use function fclose;
use function fopen;
use function json_decode;
use function stream_get_contents;
use function strtoupper;
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
        $data = RootZoneDatabaseConverter::toArray($content);
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

    /**
     * @return Iterator<EffectiveTLD>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->records as $tld) {
            yield Suffix::fromUnknown(Domain::fromIDNA2008($tld)->toAscii());
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

    public function toString(): string
    {
        $output = '# Version '.$this->version
            .', Last Updated '.$this->lastUpdated->format(self::IANA_DATE_FORMAT)."\n";

        foreach ($this->records as $suffix) {
            $output .= strtoupper($suffix)."\n";
        }

        return $output;
    }

    /**
     * @param mixed $host a type that supports instantiating a Domain from.
     */
    public function resolve($host): ResolvedDomainName
    {
        try {
            $domain = $this->validateDomain($host);

            return new ResolvedDomain($domain, $this->fetchEffectiveTLD($domain));
        } catch (UnableToResolveDomain $exception) {
            return new ResolvedDomain($exception->getDomain());
        } catch (SyntaxError $exception) {
            return new ResolvedDomain(Domain::fromIDNA2008(null));
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

        if ((2 > count($domain)) || ('.' === substr($domain->toString(), -1, 1))) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain;
    }

    private function fetchEffectiveTLD(DomainName $domain): ?EffectiveTLD
    {
        $label = $domain->toAscii()->label(0);
        foreach ($this as $tld) {
            if ($tld->value() === $label) {
                $publicSuffix = $domain->isIdna2008() ? Domain::fromIDNA2008($domain->label(0)) : Domain::fromIDNA2003($domain->label(0));

                return Suffix::fromIANA($publicSuffix);
            }
        }

        return null;
    }

    /**
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function getIANADomain($domain): ResolvedDomainName
    {
        $domain = $this->validateDomain($domain);
        $publicSuffix = $this->fetchEffectiveTLD($domain);
        if (null === $publicSuffix) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'IANA');
        }

        return new ResolvedDomain($domain, Suffix::fromIANA($publicSuffix));
    }
}
