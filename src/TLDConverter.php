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
use Pdp\Exception\CouldNotLoadTLDs;
use SplTempFileObject;
use function preg_match;
use function sprintf;
use function strpos;
use function trim;
use const DATE_ATOM;

/**
 * IANA Root Zone Database Parser.
 *
 * This class convert the IANA Root Zone Database into an associative, multidimensional array
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class TLDConverter
{
    private const IANA_DATE_FORMAT = 'D M d H:i:s Y e';

    /**
     * Converts the IANA Root Zone Database into a TopLevelDomains associative array.
     *
     * @param string $content
     *
     * @throws CouldNotLoadTLDs if the content is invalid or can not be correctly parsed and converted
     *
     * @return array
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

            throw new CouldNotLoadTLDs(sprintf('Invalid line content: %s', $line));
        }

        if (isset($data['version'], $data['modifiedDate'], $data['records'])) {
            return $data;
        }

        throw new CouldNotLoadTLDs(sprintf('Invalid content: TLD conversion failed'));
    }

    /**
     * Extract IANA Root Zone Database header info.
     *
     * @param string $content
     *
     * @throws CouldNotLoadTLDs if the Header line is invalid
     *
     * @return array
     */
    private function extractHeader(string $content): array
    {
        if (1 !== preg_match('/^\# Version (?<version>\d+), Last Updated (?<date>.*?)$/', $content, $matches)) {
            throw new CouldNotLoadTLDs(sprintf('Invalid Version line: %s', $content));
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
     * @param string $content
     *
     * @throws CouldNotLoadTLDs If the Root Zone is invalid
     *
     * @return string
     */
    private function extractRootZone(string $content): string
    {
        try {
            $tld = (new PublicSuffix($content))->toAscii();
        } catch (Exception $exception) {
            throw new CouldNotLoadTLDs(sprintf('Invalid Root zone: %s', $content), 0, $exception);
        }

        if (1 !== $tld->count() || '' === $tld->getContent()) {
            throw new CouldNotLoadTLDs(sprintf('Invalid Root zone: %s', $content));
        }

        return (string) $tld;
    }
}
