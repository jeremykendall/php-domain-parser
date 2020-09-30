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

namespace Pdp\Storage;

use Pdp\PublicSuffixList;

interface PublicSuffixListCache
{
    /**
     * Retrieves the Public Suffix List from Cache.
     */
    public function fetch(string $uri): ?PublicSuffixList;

    /**
     * Caches the Public Suffix List.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function store(string $uri, PublicSuffixList $publicSuffixList): bool;

    /**
     * Deletes the Public Suffix List entry.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function delete(string $uri): bool;
}
