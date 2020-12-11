<?php

declare(strict_types=1);

namespace Pdp;

use function idn_to_ascii;
use function idn_to_utf8;
use function preg_match;
use function rawurldecode;
use function strpos;
use function strtolower;
use const IDNA_CHECK_BIDI;
use const IDNA_CHECK_CONTEXTJ;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;
use const IDNA_USE_STD3_RULES;
use const INTL_IDNA_VARIANT_UTS46;

final class IntlIdna
{
    public const IDNA2008_ASCII = IDNA_NONTRANSITIONAL_TO_ASCII
        | IDNA_CHECK_BIDI
        | IDNA_USE_STD3_RULES
        | IDNA_CHECK_CONTEXTJ;

    public const IDNA2008_UNICODE = IDNA_NONTRANSITIONAL_TO_UNICODE
        | IDNA_CHECK_BIDI
        | IDNA_USE_STD3_RULES
        | IDNA_CHECK_CONTEXTJ;

    public const IDNA2003_ASCII = IDNA_DEFAULT;
    public const IDNA2003_UNICODE = IDNA_DEFAULT;

    private const REGEXP_IDNA_PATTERN = '/[^\x20-\x7f]/';

    /**
     * Converts the input to its IDNA ASCII form.
     *
     * This method returns the string converted to IDN ASCII form
     *
     * @throws SyntaxError if the string can not be converted to ASCII using IDN UTS46 algorithm
     */
    public static function toAscii(string $domain, int $option): IdnaResult
    {
        $domain = rawurldecode($domain);
        if (1 !== preg_match(self::REGEXP_IDNA_PATTERN, $domain)) {
            return IdnaResult::fromIntl([
                'result' => strtolower($domain),
                'isTransitionalDifferent' => false,
                'errors' => 0,
            ]);
        }

        idn_to_ascii($domain, $option, INTL_IDNA_VARIANT_UTS46, $infos);

        return self::createIdnaResult($domain, $infos);
    }

    /**
     * Converts the input to its IDNA UNICODE form.
     *
     * This method returns the string converted to IDN UNICODE form
     *
     * @throws SyntaxError if the string can not be converted to UNICODE using IDN UTS46 algorithm
     */
    public static function toUnicode(string $domain, int $option): IdnaResult
    {
        if (false === strpos($domain, 'xn--')) {
            return IdnaResult::fromIntl([
                'result' => $domain,
                'isTransitionalDifferent' => false,
                'errors' => 0,
            ]);
        }

        idn_to_utf8($domain, $option, INTL_IDNA_VARIANT_UTS46, $infos);

        return self::createIdnaResult($domain, $infos);
    }

    /**
     * @param array{result:string, isTransitionalDifferent:bool, errors:int} $infos
     */
    private static function createIdnaResult(string $domain, array $infos): IdnaResult
    {
        $result = IdnaResult::fromIntl($infos);
        if ([] !== $result->errors()) {
            throw SyntaxError::dueToIDNAError($domain, $result);
        }

        return $result;
    }
}
