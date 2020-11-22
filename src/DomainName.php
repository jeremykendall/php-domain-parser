<?php

declare(strict_types=1);

namespace Pdp;

use Iterator;
use IteratorAggregate;

/**
 * @see https://tools.ietf.org/html/rfc1034#section-3.5
 * @see https://tools.ietf.org/html/rfc1123#section-2.1
 * @see https://tools.ietf.org/html/rfc5890
 */
interface DomainName extends Host, IteratorAggregate
{
    /**
     * Tells whether IDNA Conversion is done using IDNA2008 algorithm.
     */
    public function isIdna2008(): bool;

    /**
     * Tells whether the current domain is in its ascii form.
     */
    public function isAscii(): bool;

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
    public function getIterator(): Iterator;

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
     * @throws CannotProcessHost If the key is out of bounds
     * @throws CannotProcessHost If the label is converted to the NULL value
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
     * @throws CannotProcessHost If the key is out of bounds
     */
    public function withoutLabel(int $key, int ...$keys): self;
}
