<?php

declare(strict_types=1);

namespace Pdp;

use UnexpectedValueException;
use function idn_to_ascii;
use function idn_to_utf8;
use function implode;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function strpos;
use function strtolower;
use const IDNA_CHECK_BIDI;
use const IDNA_CHECK_CONTEXTJ;
use const IDNA_DEFAULT;
use const IDNA_ERROR_BIDI;
use const IDNA_ERROR_CONTEXTJ;
use const IDNA_ERROR_DISALLOWED;
use const IDNA_ERROR_DOMAIN_NAME_TOO_LONG;
use const IDNA_ERROR_EMPTY_LABEL;
use const IDNA_ERROR_HYPHEN_3_4;
use const IDNA_ERROR_INVALID_ACE_LABEL;
use const IDNA_ERROR_LABEL_HAS_DOT;
use const IDNA_ERROR_LABEL_TOO_LONG;
use const IDNA_ERROR_LEADING_COMBINING_MARK;
use const IDNA_ERROR_LEADING_HYPHEN;
use const IDNA_ERROR_PUNYCODE;
use const IDNA_ERROR_TRAILING_HYPHEN;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;
use const IDNA_USE_STD3_RULES;
use const INTL_IDNA_VARIANT_UTS46;

final class IntlIdna
{
    public const IDNA2008_ASCII_OPTIONS = IDNA_NONTRANSITIONAL_TO_ASCII
        | IDNA_CHECK_BIDI
        | IDNA_USE_STD3_RULES
        | IDNA_CHECK_CONTEXTJ;

    public const IDNA2008_UNICODE_OPTIONS = IDNA_NONTRANSITIONAL_TO_UNICODE
        | IDNA_CHECK_BIDI
        | IDNA_USE_STD3_RULES
        | IDNA_CHECK_CONTEXTJ;

    public const IDNA2003_ASCII_OPTIONS = IDNA_DEFAULT;
    public const IDNA2003_UNICODE_OPTIONS = IDNA_DEFAULT;

    /**
     * IDNA errors.
     *
     * @see http://icu-project.org/apiref/icu4j/com/ibm/icu/text/IDNA.Error.html
     */
    private const IDNA_ERRORS = [
        IDNA_ERROR_EMPTY_LABEL => 'a non-final domain name label (or the whole domain name) is empty',
        IDNA_ERROR_LABEL_TOO_LONG => 'a domain name label is longer than 63 bytes',
        IDNA_ERROR_DOMAIN_NAME_TOO_LONG => 'a domain name is longer than 255 bytes in its storage form',
        IDNA_ERROR_LEADING_HYPHEN => 'a label starts with a hyphen-minus ("-")',
        IDNA_ERROR_TRAILING_HYPHEN => 'a label ends with a hyphen-minus ("-")',
        IDNA_ERROR_HYPHEN_3_4 => 'a label contains hyphen-minus ("-") in the third and fourth positions',
        IDNA_ERROR_LEADING_COMBINING_MARK => 'a label starts with a combining mark',
        IDNA_ERROR_DISALLOWED => 'a label or domain name contains disallowed characters',
        IDNA_ERROR_PUNYCODE => 'a label starts with "xn--" but does not contain valid Punycode',
        IDNA_ERROR_LABEL_HAS_DOT => 'a label contains a dot=full stop',
        IDNA_ERROR_INVALID_ACE_LABEL => 'An ACE label does not contain a valid label string',
        IDNA_ERROR_BIDI => 'a label does not meet the IDNA BiDi requirements (for right-to-left characters)',
        IDNA_ERROR_CONTEXTJ => 'a label does not meet the IDNA CONTEXTJ requirements',
    ];

    private const REGEXP_IDNA_PATTERN = '/[^\x20-\x7f]/';

    /**
     * Get and format IDN conversion error message.
     */
    private static function getIDNAErrors(int $errorByte): string
    {
        $res = [];
        foreach (self::IDNA_ERRORS as $error => $reason) {
            if ($error === ($errorByte & $error)) {
                $res[] = $reason;
            }
        }

        return [] === $res ? 'Unknown IDNA conversion error.' : implode(', ', $res).'.';
    }

    /**
     * Converts the input to its IDNA ASCII form.
     *
     * This method returns the string converted to IDN ASCII form
     *
     * @throws SyntaxError if the string can not be converted to ASCII using IDN UTS46 algorithm
     */
    public static function toAscii(string $domain, int $option): string
    {
        $domain = rawurldecode($domain);
        if (1 !== preg_match(self::REGEXP_IDNA_PATTERN, $domain)) {
            return strtolower($domain);
        }

        $output = idn_to_ascii($domain, $option, INTL_IDNA_VARIANT_UTS46, $infos);
        if ([] === $infos) {
            throw SyntaxError::dueToIDNAError($domain);
        }

        if (0 !== $infos['errors']) {
            throw SyntaxError::dueToIDNAError($domain, self::getIDNAErrors($infos['errors']));
        }

        // @codeCoverageIgnoreStart
        if (false === $output) {
            throw new UnexpectedValueException(sprintf('The Intl extension is misconfigured for %s, please correct this issue before proceeding.', PHP_OS));
        }
        // @codeCoverageIgnoreEnd

        if (false === strpos($output, '%')) {
            return $output;
        }

        throw SyntaxError::dueToInvalidCharacters($domain);
    }

    /**
     * Converts the input to its IDNA UNICODE form.
     *
     * This method returns the string converted to IDN UNICODE form
     *
     * @throws SyntaxError              if the string can not be converted to UNICODE using IDN UTS46 algorithm
     * @throws UnexpectedValueException if the intl extension is misconfigured
     */
    public static function toUnicode(string $domain, int $option): string
    {
        if (false === strpos($domain, 'xn--')) {
            return $domain;
        }

        $output = idn_to_utf8($domain, $option, INTL_IDNA_VARIANT_UTS46, $info);
        if ([] === $info) {
            throw SyntaxError::dueToIDNAError($domain);
        }

        if (0 !== $info['errors']) {
            throw SyntaxError::dueToIDNAError($domain, self::getIDNAErrors($info['errors']));
        }

        // @codeCoverageIgnoreStart
        if (false === $output) {
            throw new UnexpectedValueException(sprintf('The Intl extension for %s is misconfigured. Please correct this issue before proceeding.', PHP_OS));
        }
        // @codeCoverageIgnoreEnd

        return $output;
    }
}
