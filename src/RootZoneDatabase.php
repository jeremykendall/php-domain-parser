<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use Countable;
use DateTimeImmutable;
use IteratorAggregate;
use JsonSerializable;

interface RootZoneDatabase extends Countable, DomainResolver, IteratorAggregate, JsonSerializable
{
    /**
     * Returns the Version ID.
     */
    public function getVersion(): string;

    /**
     * Returns the List Last Modified Date.
     */
    public function getModifiedDate(): DateTimeImmutable;

    /**
     * {@inheritdoc}
     */
    public function count(): int;

    /**
     * Tells whether the list is empty.
     */
    public function isEmpty(): bool;

    /**
     * {@inheritdoc}
     */
    public function getIterator();

    /**
     * Returns an array representation of the list.
     */
    public function jsonSerialize(): array;

    /**
     * Tells whether the submitted host is a valid Top Level Domain.
     */
    public function contains(Host $tld): bool;
}
