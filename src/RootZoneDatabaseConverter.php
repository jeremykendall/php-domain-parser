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

use DateTimeImmutable;
use SplTempFileObject;
use function preg_match;
use function strpos;
use function trim;
use const DATE_ATOM;

/**
 * IANA Root Zone Database Converter.
 *
 * This class convert the IANA Root Zone Database into an associative, multidimensional array
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class RootZoneDatabaseConverter
{
    private const IANA_DATE_FORMAT = 'D M d H:i:s Y e';

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @throws UnableToLoadRootZoneDatabase if the content is invalid or can not be correctly parsed and converted
     */
    public function convert(string $content): array
    {
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
            'modifiedDate' => $date->format(DATE_ATOM),
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
            $tld = PublicSuffix::fromUnknownSection($content)->toAscii();
        } catch (ExceptionInterface $exception) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content, $exception);
        }

        if (1 !== $tld->count() || '' === $tld->getContent()) {
            throw UnableToLoadRootZoneDatabase::dueToInvalidRootZoneDomain($content);
        }

        return (string) $tld;
    }
}
