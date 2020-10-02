<?php

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
    public function remember(string $uri, PublicSuffixList $publicSuffixList): bool;

    /**
     * Deletes the Public Suffix List entry.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function forget(string $uri): bool;
}
