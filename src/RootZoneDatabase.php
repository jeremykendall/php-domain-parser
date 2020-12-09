<?php

declare(strict_types=1);

namespace Pdp;

use Countable;
use DateTimeImmutable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * @extends IteratorAggregate<EffectiveTopLevelDomain>
 */
interface RootZoneDatabase extends Countable, DomainNameResolver, IteratorAggregate, JsonSerializable
{
    /**
     * Returns the Version ID.
     */
    public function version(): string;

    /**
     * Returns the List Last Modified Date.
     */
    public function lastUpdated(): DateTimeImmutable;

    /**
     * {@inheritdoc}
     */
    public function count(): int;

    /**
     * Tells whether the list is empty.
     */
    public function isEmpty(): bool;

    /**
     * @return Iterator<EffectiveTopLevelDomain>
     */
    public function getIterator(): Iterator;

    /**
     * Returns an array representation of the list.
     *
     * @return array{version:string, records:array<string>, lastUpdated:string}
     */
    public function jsonSerialize(): array;

    /**
     * Returns PSL info for a given domain against the PSL rules for ICANN domain detection.
     *
     * @throws SyntaxError           if the domain is invalid
     * @throws UnableToResolveDomain if the domain does not contain a IANA Effective TLD
     */
    public function getIANADomain(Host $host): ResolvedDomainName;
}
