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

use Countable;
use JsonSerializable;

/**
 * @see https://tools.ietf.org/html/rfc1034#section-3.5
 * @see https://tools.ietf.org/html/rfc1123#section-2.1
 * @see https://tools.ietf.org/html/rfc5890
 */
interface Host extends Countable, JsonSerializable, IDNConversion
{
    /**
     * Returns the domain content.
     */
    public function getContent(): ?string;

    /**
     * {@inheritdoc}
     *
     * The labels total number.
     */
    public function count(): int;

    /**
     * Returns the domain content.
     */
    public function jsonSerialize(): ?string;

    /**
     * Returns the domain content as a string.
     */
    public function __toString(): string;

    /**
     * Converts the domain to its IDNA ASCII form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with its content converted to its IDNA ASCII form
     *
     * @throws ExceptionInterface if the domain can not be converted to ASCII using IDN UTS46 algorithm
     *
     * @return static
     */
    public function toAscii(): self;

    /**
     * Converts the domain to its IDNA UTF8 form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with its content converted to its IDNA UTF8 form
     *
     * @throws ExceptionInterface if the domain can not be converted to Unicode using IDN UTS46 algorithm
     *
     * @return static
     */
    public function toUnicode(): self;
}
