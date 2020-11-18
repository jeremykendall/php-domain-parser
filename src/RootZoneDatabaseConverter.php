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
    private const IANA_DATE_FORMAT = 'D M d H:i:s Y e';

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @param object|string $content The object should expose the __toString method
     *
     * @throws UnableToLoadRootZoneDatabase if the content is invalid or can not be correctly parsed and converted
     */
    public function convert($content): array
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
                $data = $this->extractHeader($line);
                continue;
            }

            if (false === strpos($line, '#')) {
                $data['records'] = $data['records'] ?? [];
                $data['records'][] = $this->extractRootZone($line);
                continue;
            }

            throw UnableToLoadRootZoneDatabase::dueToInvalidLine($line);
        }

        if (isset($data['version'], $data['modifiedDate'], $data['records'])) {
            return $data;
        }

        throw UnableToLoadRootZoneDatabase::dueToFailedConversion();
    }

    /**
     * Extract IANA Root Zone Database header info.
     *
     * @throws UnableToLoadRootZoneDatabase if the Header line is invalid
     */
    private function extractHeader(string $content): array
    {
        if (1 !== preg_match('/^\# Version (?<version>\d+), Last Updated (?<date>.*?)$/', $content, $matches)) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidVersionLine($content);
        }

        /** @var DateTimeImmutable $date */
        $date = DateTimeImmutable::createFromFormat(self::IANA_DATE_FORMAT, $matches['date']);

        return [
            'version' => $matches['version'],
            'modifiedDate' => $date->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Extract IANA Root Zone.
     *
     * @throws UnableToLoadRootZoneDatabase If the Root Zone is invalid
     */
    private function extractRootZone(string $content): string
    {
        try {
            $tld = PublicSuffix::fromUnknown(new Domain($content))->toAscii();
        } catch (CannotProcessHost $exception) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content, $exception);
        }

        if (1 !== $tld->count() || '' === $tld->value()) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content);
        }

        return $tld->toString();
    }
}
