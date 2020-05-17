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

use Pdp\Exception\CouldNotLoadRules;
use SplTempFileObject;
use function array_pop;
use function explode;
use function preg_match;
use function strpos;
use function substr;

/**
 * Public Suffix List Parser.
 *
 * This class convert the Public Suffix List into an associative, multidimensional array
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Converter implements PublicSuffixListSection
{
    use IDNAConverterTrait;

    private const PSL_SECTION = [
        'ICANN' => [
            'BEGIN' => self::ICANN_DOMAINS,
            'END' => '',
        ],
        'PRIVATE' => [
            'BEGIN' => self::PRIVATE_DOMAINS,
            'END' => '',
        ],
    ];

    private const REGEX_PSL_SECTION = ',^// ===(?<point>BEGIN|END) (?<type>ICANN|PRIVATE) DOMAINS===,';

    /**
     * Convert the Public Suffix List into
     * an associative, multidimensional array.
     *
     * @param string $content
     *
     * @return array
     */
    public function convert(string $content): array
    {
        $rules = [self::ICANN_DOMAINS => [], self::PRIVATE_DOMAINS => []];
        $section = '';
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        /** @var string $line */
        foreach ($file as $line) {
            $section = $this->getSection($section, $line);
            if ('' !== $section && false === strpos($line, '//')) {
                $rules[$section] = $this->addRule($rules[$section], explode('.', $line));
            }
        }

        return $rules;
    }

    /**
     * Returns the section type for a given line.
     *
     * @param string $section the current status
     * @param string $line    the current file line
     *
     * @return string
     */
    private function getSection(string $section, string $line): string
    {
        if (1 === preg_match(self::REGEX_PSL_SECTION, $line, $matches)) {
            return self::PSL_SECTION[$matches['type']][$matches['point']];
        }

        return $section;
    }

    /**
     * Recursive method to build the array representation of the Public Suffix List.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * @see https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param array $list       Initially an empty array, this eventually
     *                          becomes the array representation of a Public Suffix List section
     * @param array $rule_parts One line (rule) from the Public Suffix List
     *                          exploded on '.', or the remaining portion of that array during recursion
     *
     * @throws CouldNotLoadRules if the domain name can not be converted using IDN to ASCII algorithm
     *
     * @return array
     */
    private function addRule(array $list, array $rule_parts): array
    {
        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."
        try {
            $rule = $this->idnToAscii(array_pop($rule_parts));
        } catch (Exception $e) {
            throw new CouldNotLoadRules($e->getMessage(), $e->getCode(), $e);
        }

        $isDomain = true;
        if (0 === strpos($rule, '!')) {
            $rule = substr($rule, 1);
            $isDomain = false;
        }

        $list[$rule] = $list[$rule] ?? ($isDomain ? [] : ['!' => '']);
        if ($isDomain && [] !== $rule_parts) {
            $list[$rule] = $this->addRule($list[$rule], $rule_parts);
        }

        return $list;
    }
}
