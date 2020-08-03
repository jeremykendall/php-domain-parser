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

use Pdp\Exception\InvalidDomain;
use TypeError;
use UnexpectedValueException;
use function array_reverse;
use function explode;
use function filter_var;
use function gettype;
use function idn_to_ascii;
use function idn_to_utf8;
use function implode;
use function is_scalar;
use function method_exists;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function strpos;
use function strtolower;
use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;
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
use const INTL_IDNA_VARIANT_UTS46;

/**
 * @internal Domain name validator
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
trait IDNAConverterTrait
{
    /**
     * Get and format IDN conversion error message.
     *
     * @param int $error_byte
     *
     * @return string
     */
    private static function getIdnErrors(int $error_byte): string
    {
        /**
         * IDNA errors.
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
            if ($error === ($error_byte & $error)) {
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
     * @param string $domain
     *
     * @param int $option
     *
     * @throws InvalidDomain if the string can not be converted to ASCII using IDN UTS46 algorithm
     *
     * @return string
     */
    private function idnToAscii(string $domain, int $option = IDNA_DEFAULT): string
    {
        list($domain, ) = $this->transformToAscii($domain, $option);

        return $domain;
    }

    /**
     * Returns the IDNA ASCII form and its isTransitionalDifferent state.
     *
     * @param string $domain
     *
     * @param int $option
     *
     * @throws InvalidDomain if the string can not be converted to ASCII using IDN UTS46 algorithm
     *
     * @return array
     */
    private function transformToAscii(string $domain, int $option): array
    {
        $domain = rawurldecode($domain);

        static $pattern = '/[^\x20-\x7f]/';

        if (1 !== preg_match($pattern, $domain)) {
            return [strtolower($domain), ['isTransitionalDifferent' => false]];
        }

        $output = idn_to_ascii($domain, $option, INTL_IDNA_VARIANT_UTS46, $infos);
        if ([] === $infos) {
            throw new InvalidDomain(sprintf('The host `%s` is invalid', $domain));
        }

        if (0 !== $infos['errors']) {
            throw new InvalidDomain(sprintf('The host `%s` is invalid : %s', $domain, self::getIdnErrors($infos['errors'])));
        }

        // @codeCoverageIgnoreStart
        if (false === $output) {
            throw new UnexpectedValueException(sprintf('The Intl extension is misconfigured for %s, please correct this issue before proceeding.', PHP_OS));
        }
        // @codeCoverageIgnoreEnd

        if (false === strpos($output, '%')) {
            return [$output, $infos];
        }

        throw new InvalidDomain(sprintf('The host `%s` is invalid: it contains invalid characters', $domain));
    }
    /**
     * Converts the input to its IDNA UNICODE form.
     *
     * This method returns the string converted to IDN UNICODE form
     *
     * @param string $domain
     *
     * @param int $option
     *
     * @throws InvalidDomain            if the string can not be converted to UNICODE using IDN UTS46 algorithm
     * @throws UnexpectedValueException if the intl extension is misconfigured
     *
     * @return string
     */
    private function idnToUnicode(string $domain, int $option = IDNA_DEFAULT): string
    {
        $output = idn_to_utf8($domain, $option, INTL_IDNA_VARIANT_UTS46, $info);
        if ([] === $info) {
            throw new InvalidDomain(sprintf('The host `%s` is invalid', $domain));
        }

        if (0 !== $info['errors']) {
            throw new InvalidDomain(sprintf('The host `%s` is invalid : %s', $domain, self::getIdnErrors($info['errors'])));
        }

        // @codeCoverageIgnoreStart
        if (false === $output) {
            throw new UnexpectedValueException(sprintf('The Intl extension is misconfigured for %s, please correct this issue before proceeding.', PHP_OS));
        }
        // @codeCoverageIgnoreEnd

        return $output;
    }

    /**
     * Filter and format the domain to ensure it is valid.
     * Returns an array containing the formatted domain name in lowercase
     * with its associated labels in reverse order
     * For example: parse('wWw.uLb.Ac.be') should return ['be', 'ac', 'ulb', 'www'];.
     *
     * @deprecated
     *
     * @codeCoverageIgnore
     *
     * @param mixed $domain
     *
     * @throws InvalidDomain If the domain is invalid
     *
     * @return string[]
     */
    private function setLabels($domain = null): array
    {
        return $this->parse($domain, IDNA_DEFAULT, IDNA_DEFAULT)['labels'];
    }

    /**
     * Parse and format the domain to ensure it is valid.
     * Returns an array containing the formatted domain name labels
     * and the domain transitional information.
     *
     * For example: parse('wWw.uLb.Ac.be') should return
     *     ['labels' => ['be', 'ac', 'ulb', 'www'], 'isTransitionalDifferant' => false];.
     *
     * @param mixed $domain
     * @param int   $asciiOption
     * @param int   $unicodeOption
     *
     * @throws InvalidDomain If the domain is invalid
     *
     * @return array
     */
    private function parse($domain = null, int $asciiOption = 0, int $unicodeOption = 0): array
    {
        if ($domain instanceof DomainInterface) {
            $domain = $domain->getContent();
        }

        if (null === $domain) {
            return ['labels' => [], 'isTransitionalDifferent' => false];
        }

        if ('' === $domain) {
            return ['labels' => [''], 'isTransitionalDifferent' => false];
        }

        if (!is_scalar($domain) && !method_exists($domain, '__toString')) {
            throw new TypeError(sprintf('The domain must be a scalar, a stringable object, a DomainInterface object or null; `%s` given', gettype($domain)));
        }

        $domain = (string) $domain;
        $res = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (false !== $res) {
            throw new InvalidDomain(sprintf('The domain `%s` is invalid: this is an IPv4 host', $domain));
        }

        $formatted_domain = rawurldecode($domain);

        // Note that unreserved is purposely missing . as it is used to separate labels.
        static $domain_name = '/(?(DEFINE)
                (?<unreserved>[a-z0-9_~\-])
                (?<sub_delims>[!$&\'()*+,;=])
                (?<encoded>%[A-F0-9]{2})
                (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded)){1,63})
            )
            ^(?:(?&reg_name)\.){0,126}(?&reg_name)\.?$/ix';
        if (1 === preg_match($domain_name, $formatted_domain)) {
            return [
                'labels' => array_reverse(explode('.', strtolower($formatted_domain))),
                'isTransitionalDifferent' => false,
            ];
        }

        // a domain name can not contains URI delimiters or space
        static $gen_delims = '/[:\/?#\[\]@ ]/';
        if (1 === preg_match($gen_delims, $formatted_domain)) {
            throw new InvalidDomain(sprintf('The domain `%s` is invalid: it contains invalid characters', $domain));
        }

        // if the domain name does not contains UTF-8 chars then it is malformed
        static $pattern = '/[^\x20-\x7f]/';
        if (1 !== preg_match($pattern, $formatted_domain)) {
            throw new InvalidDomain(sprintf('The domain `%s` is invalid: the labels are malformed', $domain));
        }

        list($ascii_domain, $infos) = $this->transformToAscii($domain, $asciiOption);
        $infos['labels'] = array_reverse(explode('.', $this->idnToUnicode($ascii_domain, $unicodeOption)));

        return $infos;
    }
}
