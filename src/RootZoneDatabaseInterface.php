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
use DateTimeImmutable;
use IteratorAggregate;
use JsonSerializable;

interface RootZoneDatabaseInterface extends Countable, IteratorAggregate, JsonSerializable
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
     * Tells whether the submitted TLD is a valid Top Level Domain.
     *
     * @param mixed $tld a TLD in a type that can be converted into a DomainInterface instance
     */
    public function contains($tld): bool;

    /**
     * Returns a domain where its public suffix is the found TLD.
     *
     * @param mixed $domain a domain in a type that can be converted into a DomainInterface instance
     */
    public function resolve($domain): ResolvedHostInterface;

    /**
     * Sets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withAsciiIDNAOption(int $option): RootZoneDatabaseInterface;

    /**
     * Sets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function withUnicodeIDNAOption(int $option): RootZoneDatabaseInterface;
}
