<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeInterface;
use SplTempFileObject;
use TypeError;
use function gettype;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function strpos;
use function trim;

final class RootZoneDatabaseConverter
{
    private const REGEXP_HEADER_LINE = '/^\# Version (?<version>\d+), Last Updated (?<date>.*?)$/';

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @param object|string $content The object should expose the __toString method
     *
     * @throws UnableToLoadRootZoneDatabase if the content is invalid or can not be correctly parsed and converted
     */
    public static function toArray($content): array
    {
        if (is_object($content) && method_exists($content, '__toString')) {
            $content = (string) $content;
        }

        if (!is_string($content)) {
            throw new TypeError('The content to be converted should be a string or a Stringable object, `'.gettype($content).'` given.');
        }

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
     */
    private static function extractHeader(string $content): array
    {
        if (1 !== preg_match(self::REGEXP_HEADER_LINE, $content, $matches)) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidVersionLine($content);
        }

        /** @var DateTimeImmutable $date */
        $date = DateTimeImmutable::createFromFormat(RootZoneDatabase::IANA_DATE_FORMAT, $matches['date']);

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
}
