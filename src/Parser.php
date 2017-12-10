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
 * This class parses the Public Suffix List
 */
final class Parser
{
    /**
     * Convert the Public Suffix List into
     * an associative, multidimensional array
     *
     * @param string $content
     *
     * @return array
     */
    public function parse(string $content): array
    {
        $rules = [
            PublicSuffix::ICANN => [],
            PublicSuffix::PRIVATE => [],
        ];
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
        if ($section == '' && strpos($line, '// ===BEGIN ICANN DOMAINS===') === 0) {
            return PublicSuffix::ICANN;
        }

        if ($section == PublicSuffix::ICANN && strpos($line, '// ===END ICANN DOMAINS===') === 0) {
            return '';
        }

        if ($section == '' && strpos($line, '// ===BEGIN PRIVATE DOMAINS===') === 0) {
            return PublicSuffix::PRIVATE;
        }

        if ($section == PublicSuffix::PRIVATE && strpos($line, '// ===END PRIVATE DOMAINS===') === 0) {
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
        if (strpos($part, '!') === 0) {
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
