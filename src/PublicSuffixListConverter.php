<?php

declare(strict_types=1);

namespace Pdp;

use SplTempFileObject;
use TypeError;
use function array_pop;
use function explode;
use function gettype;
use function is_object;
use function method_exists;
use function preg_match;
use function strpos;
use function substr;

final class PublicSuffixListConverter
{
    private const PSL_SECTION = [
        'ICANN' => [
            'BEGIN' => PublicSuffixList::ICANN_DOMAINS,
            'END' => '',
        ],
        'PRIVATE' => [
            'BEGIN' => PublicSuffixList::PRIVATE_DOMAINS,
            'END' => '',
        ],
    ];

    private const REGEX_PSL_SECTION = ',^// ===(?<point>BEGIN|END) (?<type>ICANN|PRIVATE) DOMAINS===,';

    /**
     * Convert the Public Suffix List into an associative, multidimensional array.
     *
     * @param object|string $content The object should expose the __toString method
     */
    public static function toArray($content): array
    {
        if (is_object($content) && method_exists($content, '__toString')) {
            $content = (string) $content;
        }

        if (!is_string($content)) {
            throw new TypeError('The content to be converted should be a string or a Stringable object, `'.gettype($content).'` given.');
        }

        $rules = [];
        $section = '';
        $file = new SplTempFileObject();
        $file->fwrite($content);
        $file->setFlags(SplTempFileObject::DROP_NEW_LINE | SplTempFileObject::READ_AHEAD | SplTempFileObject::SKIP_EMPTY);
        /** @var string $line */
        foreach ($file as $line) {
            $section = self::getSection($section, $line);
            if ('' !== $section && false === strpos($line, '//')) {
                $rules[$section] = $rules[$section] ?? [];
                $rules[$section] = self::addRule($rules[$section], explode('.', $line));
            }
        }

        return $rules;
    }

    /**
     * Returns the section type for a given line.
     */
    private static function getSection(string $section, string $line): string
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
     * @param array $list      Initially an empty array, this eventually
     *                         becomes the array representation of a Public Suffix List section
     * @param array $ruleParts One line (rule) from the Public Suffix List
     *                         exploded on '.', or the remaining portion of that array during recursion
     *
     * @throws UnableToLoadPublicSuffixList if the domain name can not be converted using IDN to ASCII algorithm
     */
    private static function addRule(array $list, array $ruleParts): array
    {
        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."
        try {
            /** @var string $line */
            $line = array_pop($ruleParts);
            $rule = IntlIdna::toAscii($line, IntlIdna::IDNA2008_ASCII_OPTIONS);
        } catch (CannotProcessHost $exception) {
            throw UnableToLoadPublicSuffixList::dueToInvalidRule($line ?? null, $exception);
        }

        $isDomain = true;
        if (0 === strpos($rule, '!')) {
            $rule = substr($rule, 1);
            $isDomain = false;
        }

        $list[$rule] = $list[$rule] ?? ($isDomain ? [] : ['!' => '']);
        if ($isDomain && [] !== $ruleParts) {
            $list[$rule] = self::addRule($list[$rule], $ruleParts);
        }

        return $list;
    }
}
