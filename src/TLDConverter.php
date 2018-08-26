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
use const DATE_ATOM;
use function preg_match;
use function sprintf;
use function strpos;
use function trim;

/**
 * IANA Root Zone Database Parser.
 *
 * This class convert the IANA Root Zone Databas into an associative, multidimensional array
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class TLDConverter
{
    /**
     * @internal
     */
    const IANA_DATE_FORMAT = 'D M d H:i:s Y e';

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @throws Exception if the content is invalid or can not be correctly parsed and converted
     */
    public function convert(string $content): array
    {
        $data = [];
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
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

            throw new Exception(sprintf('Invalid line content: %s', $line));
        }

        if (isset($data['version'], $data['update'], $data['records'])) {
            return $data;
        }

        throw new Exception(sprintf('Invalid content: TLD conversion failed'));
    }

    /**
     * Extract IANA Root Zone Database header info.
     *
     * @throws Exception if the Header line is invalid
     */
    private function extractHeader(string $content): array
    {
        if (!preg_match('/^\# Version (?<version>\d+), Last Updated (?<update>.*?)$/', $content, $matches)) {
            throw new Exception(sprintf('Invalid Version line: %s', $content));
        }

        return [
            'version' => $matches['version'],
            'update' => DateTimeImmutable::createFromFormat(self::IANA_DATE_FORMAT, $matches['update'])
                ->format(DATE_ATOM),
        ];
    }

    /**
     * Extract IANA Root Zone.
     *
     * @throws Exception If the Root Zone is invalid
     */
    private function extractRootZone(string $content): string
    {
        $tld = (new PublicSuffix($content))->toAscii();
        if (1 !== $tld->count() || '' === $tld->getContent()) {
            throw new Exception(sprintf('Invalid Root zone: %s', $content));
        }

        return (string) $tld;
    }
}
