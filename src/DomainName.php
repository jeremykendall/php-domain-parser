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

use IteratorAggregate;

/**
 * @see https://tools.ietf.org/html/rfc1034#section-3.5
 * @see https://tools.ietf.org/html/rfc1123#section-2.1
 * @see https://tools.ietf.org/html/rfc5890
 */
interface DomainName extends Host, IteratorAggregate
{
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
    public function label(int $key): ?string;

    /**
     * Returns the object labels.
     *
     * @return array<string>
     */
    public function labels(): array;

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @return array<int>
     */
    public function keys(string $label = null): array;

    /**
     * {@inheritdoc}
     *
     * The external iterator iterates over the DomainInterface labels
     * from the right-most label to the left-most label.
     */
    public function getIterator();

    /**
     * Prepends a label to the domain.
     *
     * @see ::withLabel
     *
     * @param mixed $label a domain label
     */
    public function prepend($label): self;

    /**
     * Appends a label to the domain.
     *
     * @see ::withLabel
     *
     * @param mixed $label a domain label
     */
    public function append($label): self;

    /**
     * Returns an instance with the specified label added at the specified key.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new label
     *
     * If $key is non-negative, the added label will be the label at $key position from the start.
     * If $key is negative, the added label will be the label at $key position from the end.
     *
     * @param mixed $label a domain label
     *
     * @throws ExceptionInterface If the key is out of bounds
     * @throws ExceptionInterface If the label is converted to the NULL value
     */
    public function withLabel(int $key, $label): self;

    /**
     * Returns an instance with the label at the specified key removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the specified label
     *
     * If $key is non-negative, the removed label will be the label at $key position from the start.
     * If $key is negative, the removed label will be the label at $key position from the end.
     *
     * @param int ...$keys
     *
     * @throws ExceptionInterface If the key is out of bounds
     */
    public function withoutLabel(int $key, int ...$keys): self;
}
