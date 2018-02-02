<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

use SplTempFileObject;

/**
 * Public Suffix List Parser.
 *
 * This class convert the Public Suffix List into an associative, multidimensional array
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Converter
{
    /**
     * Convert the Public Suffix List into
     * an associative, multidimensional array
     *
     * @param string $content
     *
     * @return array
     */
    public function convert(string $content): array
    {
        $rules = [Rules::ICANN_DOMAINS => [], Rules::PRIVATE_DOMAINS => []];
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        $section = '';
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
        if ('' === $section && 0 === strpos($line, '// ===BEGIN ICANN DOMAINS===')) {
            return Rules::ICANN_DOMAINS;
        }

        if (Rules::ICANN_DOMAINS === $section && 0 === strpos($line, '// ===END ICANN DOMAINS===')) {
            return '';
        }

        if ('' === $section && 0 === strpos($line, '// ===BEGIN PRIVATE DOMAINS===')) {
            return Rules::PRIVATE_DOMAINS;
        }

        if (Rules::PRIVATE_DOMAINS === $section && 0 === strpos($line, '// ===END PRIVATE DOMAINS===')) {
            return '';
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
     *                          becomes the array representation of the Public Suffix List
     * @param array $rule_parts One line (rule) from the Public Suffix List
     *                          exploded on '.', or the remaining portion of that array during recursion
     *
     * @return array
     */
    private function addRule(array $list, array $rule_parts): array
    {
        $part = array_pop($rule_parts);

        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."

        $part = idn_to_ascii($part, 0, INTL_IDNA_VARIANT_UTS46);
        $isDomain = true;
        if (0 === strpos($part, '!')) {
            $part = substr($part, 1);
            $isDomain = false;
        }

        if (!isset($list[$part])) {
            $list[$part] = $isDomain ? [] : ['!' => ''];
        }

        if ($isDomain && !empty($rule_parts)) {
            $list[$part] = $this->addRule($list[$part], $rule_parts);
        }

        return $list;
    }
}
