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
use IteratorAggregate;

/**
 * Domain Interface.
 *
 * @see https://tools.ietf.org/html/rfc1034#section-3.5
 * @see https://tools.ietf.org/html/rfc1123#section-2.1
 * @see https://tools.ietf.org/html/rfc5890
 */
interface DomainInterface extends Countable, IteratorAggregate
{
    /**
     * Returns the domain content.
     */
    public function getContent(): ?string;

    /**
     * Returns the domain content as a string.
     */
    public function __toString(): string;

    /**
     * Retrieves a single domain label.
     *
     * If $key is non-negative, the returned value will be the label at $key position from the start.
     * If $key is negative, the returned value will be the label at $key position from the end.
     *
     * If no label is found the submitted $key the returned value will be null.
     *
     * @param int $key the label offset
     */
    public function getLabel(int $key): ?string;

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @param string $label the total number of argument given to the method
     *
     * @return int[]
     */
    public function keys(string $label): array;

    /**
     * Converts the domain to its IDNA ASCII form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with its content converted to its IDNA ASCII form
     *
     * @throws Exception if the domain can not be converted to ASCII using IDN UTS46 algorithm
     */
    public function toAscii(): self;

    /**
     * Converts the domain to its IDNA UTF8 form.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with its content converted to its IDNA UTF8 form
     *
     * @throws Exception if the domain can not be converted to Unicode using IDN UTS46 algorithm
     */
    public function toUnicode(): self;


    /**
     * {@inheritdoc}
     *
     * The external iterator iterates over the DomainInterface labels
     * from the right-most label to the left-most label.
     */
    public function getIterator();
}
