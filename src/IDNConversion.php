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

interface IDNConversion
{
    /**
     * Gets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getAsciiIDNAOption(): int;

    /**
     * Gets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getUnicodeIDNAOption(): int;

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     *
     * @return static
     */
    public function withAsciiIDNAOption(int $option): self;

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     *
     * @return static
     */
    public function withUnicodeIDNAOption(int $option): self;
}
