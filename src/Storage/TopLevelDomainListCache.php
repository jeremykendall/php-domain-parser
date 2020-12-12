<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;

interface TopLevelDomainListCache
{
    /**
     * Retrieves the Top Level Domain List from the Cache.
     */
    public function fetch(string $uri): ?TopLevelDomainList;

    /**
     * Cache the Top Level Domain List.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function remember(string $uri, TopLevelDomainList $topLevelDomainList): bool;

    /**
     * Deletes the Top Level Domain List entry.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function forget(string $uri): bool;
}
