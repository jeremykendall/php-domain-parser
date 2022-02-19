<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use Iterator;
use SplTempFileObject;
use Stringable;
use function count;
use function in_array;
use function preg_match;
use function strpos;
use function trim;

final class TopLevelDomains implements TopLevelDomainList
{
    private const IANA_DATE_FORMAT = 'D M d H:i:s Y e';
    private const REGEXP_HEADER_LINE = '/^\# Version (?<version>\d+), Last Updated (?<date>.*?)$/';

    /**
     * @param array<string, int> $records
     */
    private function __construct(
        private array $records,
        private string $version,
        private DateTimeImmutable $lastUpdated
    ) {
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadResource           If the rules can not be loaded from the path
     * @throws UnableToLoadTopLevelDomainList If the content is invalid or can not be correctly parsed and converted
     */
    public static function fromPath(string $path, $context = null): self
    {
        return self::fromString(Stream::getContentAsString($path, $context));
    }

    /**
     * Returns a new instance from a string.
     *
     * @throws UnableToLoadTopLevelDomainList if the content is invalid or can not be correctly parsed and converted
     */
    public static function fromString(Stringable|string $content): self
    {
        $data = self::parse((string) $content);

        return new self($data['records'], $data['version'], $data['lastUpdated']);
    }

    /**
     * Converts the IANA Top Level Domain List into a TopLevelDomains associative array.
     *
     * @throws UnableToLoadTopLevelDomainList if the content is invalid or can not be correctly parsed and converted
     *
     * @return array{version:string, lastUpdated:DateTimeImmutable, records:array<string,int>}
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
                $data['records'][self::extractRootZone($line)] = 1;
                continue;
            }

            throw UnableToLoadTopLevelDomainList::dueToInvalidLine($line);
        }

        if (isset($data['version'], $data['lastUpdated'], $data['records'])) {
            return $data;
        }

        throw UnableToLoadTopLevelDomainList::dueToFailedConversion();
    }

    /**
     * Extract IANA Top Level Domain List header info.
     *
     * @throws UnableToLoadTopLevelDomainList if the Header line is invalid
     *
     * @return array{version:string, lastUpdated:DateTimeImmutable}
     */
    private static function extractHeader(string $content): array
    {
        if (1 !== preg_match(self::REGEXP_HEADER_LINE, $content, $matches)) {
            throw UnableToLoadTopLevelDomainList::dueToInvalidVersionLine($content);
        }

        /** @var DateTimeImmutable $date */
        $date = DateTimeImmutable::createFromFormat(self::IANA_DATE_FORMAT, $matches['date']);

        return [
            'version' => $matches['version'],
            'lastUpdated' => $date,
        ];
    }

    /**
     * Extract IANA Root Zone.
     *
     * @throws UnableToLoadTopLevelDomainList If the Top Level Domain is invalid
     */
    private static function extractRootZone(string $content): string
    {
        try {
            $tld = Suffix::fromIANA($content);
        } catch (CannotProcessHost $exception) {
            throw UnableToLoadTopLevelDomainList::dueToInvalidTopLevelDomain($content, $exception);
        }

        return $tld->toAscii()->toString();
    }

    /**
     * @param array{records:array<string, int>, version:string, lastUpdated:DateTimeImmutable} $properties
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
     * @return Iterator<string>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->records as $tld => $_) {
            yield $tld;
        }
    }

    public function resolve(DomainNameProvider|DomainName|Host|Stringable|string|null $host): ResolvedDomainName
    {
        try {
            $domain = $this->validateDomain($host);
            if ($this->containsTopLevelDomain($domain)) {
                return ResolvedDomain::fromIANA($domain);
            }
            return ResolvedDomain::fromUnknown($domain);
        } catch (UnableToResolveDomain $exception) {
            return ResolvedDomain::fromUnknown($exception->domain());
        } catch (SyntaxError $exception) {
            return ResolvedDomain::fromUnknown(null);
        }
    }

    /**
     * Assert the domain is valid and is resolvable.
     *
     * @throws SyntaxError           If the domain is invalid
     * @throws UnableToResolveDomain If the domain can not be resolved
     */
    private function validateDomain(DomainNameProvider|DomainName|Host|Stringable|string|null $domain): DomainName
    {
        if ($domain instanceof DomainNameProvider) {
            $domain = $domain->domain();
        }

        if (!$domain instanceof DomainName) {
            $domain = Domain::fromIDNA2008($domain);
        }

        $label = $domain->label(0);
        if (in_array($label, [null, ''], true)) {
            throw UnableToResolveDomain::dueToUnresolvableDomain($domain);
        }

        return $domain;
    }

    private function containsTopLevelDomain(DomainName $domain): bool
    {
        return isset($this->records[$domain->toAscii()->label(0)]);
    }

    public function getIANADomain(DomainNameProvider|DomainName|Host|Stringable|string|null $host): ResolvedDomainName
    {
        $domain = $this->validateDomain($host);
        if (!$this->containsTopLevelDomain($domain)) {
            throw UnableToResolveDomain::dueToMissingSuffix($domain, 'IANA');
        }

        return ResolvedDomain::fromIANA($domain);
    }
}
