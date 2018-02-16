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

/**
 * A Wrapper around INTL IDNA function
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
trait IDNAConverterTrait
{
    /**
     * Get and format IDN conversion error message
     *
     * @param int $error_bit
     *
     * @return string
     */
    private static function getIdnErrors(int $error_bit): string
    {
        /**
         * IDNA errors
         *
         * @see http://icu-project.org/apiref/icu4j/com/ibm/icu/text/IDNA.Error.html
         */
        static $idn_errors = [
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

        $res = [];
        foreach ($idn_errors as $error => $reason) {
            if ($error_bit & $error) {
                $res[] = $reason;
            }
        }

        return empty($res) ? 'Unknown IDNA conversion error.' : implode(', ', $res).'.';
    }

    /**
     * Converts the input to its IDNA ASCII form.
     *
     * This method returns the string converted to IDN ASCII form
     *
     * @param  string    $host
     * @throws Exception if the string can not be converted to ASCII using IDN UTS46 algorithm
     *
     * @return string
     */
    private function idnToAscii(string $host): string
    {
        if (false !== strpos($host, '%')) {
            $host = rawurldecode($host);
        }

        $host = strtolower($host);
        static $pattern = '/[\pL]+/u';
        if (!preg_match($pattern, $host)) {
            return $host;
        }

        $output = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (!$arr['errors']) {
            return $output;
        }

        throw new Exception(sprintf('The host `%s` is invalid : %s', $host, self::getIdnErrors($arr['errors'])));
    }

    /**
     * Converts the input to its IDNA UNICODE form.
     *
     * This method returns the string converted to IDN UNICODE form
     *
     * @param  string    $host
     * @throws Exception if the string can not be converted to UNICODE using IDN UTS46 algorithm
     *
     * @return string
     */
    private function idnToUnicode(string $host): string
    {
        $output = idn_to_utf8($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (!$arr['errors']) {
            return $output;
        }

        throw new Exception(sprintf('The host `%s` is invalid : %s', $host, self::getIdnErrors($arr['errors'])));
    }
}
