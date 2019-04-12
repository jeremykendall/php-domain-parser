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
use function compact;
use function preg_match;
use function sprintf;
use function strpos;
use function trim;
use const DATE_ATOM;
use const IDNA_DEFAULT;

/**
 * IANA Root Zone Database Parser.
 *
 * This class convert the IANA Root Zone Database into an associative, multidimensional array
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
     * @param string $content
     * @param int    $asciiIDNAOption
     * @param int    $unicodeIDNAOption
     *
     * @return array
     */
    public function convert(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): array {
        $data = [];
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        foreach ($file as $line) {
            $line = trim($line);
            if ([] === $data) {
                $data = array_merge($this->extractHeader($line), compact('asciiIDNAOption', 'unicodeIDNAOption'));
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
            return array_merge($data, compact('asciiIDNAOption', 'unicodeIDNAOption'));
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

        return [
            'version' => $matches['version'],
            'modifiedDate' => DateTimeImmutable::createFromFormat(self::IANA_DATE_FORMAT, $matches['date'])
                ->format(DATE_ATOM),
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
        } catch (Exception $e) {
            throw new CouldNotLoadTLDs(sprintf('Invalid Root zone: %s', $content), 0, $e);
        }

        if (1 !== $tld->count() || '' === $tld->getContent()) {
            throw new CouldNotLoadTLDs(sprintf('Invalid Root zone: %s', $content));
        }

        return (string) $tld;
    }
}
