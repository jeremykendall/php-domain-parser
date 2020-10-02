<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\RootZoneDatabase;

interface RootZoneDatabaseCache
{
    /**
     * Retrieves the Root Zone Database from the Cache.
     */
    public function fetch(string $uri): ?RootZoneDatabase;

    /**
     * Cache the Root Zone Database.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function remember(string $uri, RootZoneDatabase $rootZoneDatabase): bool;

    /**
     * Deletes the Root Zone Database entry.
     *
     * Returns true if the action was successful, false otherwise
     */
    public function forget(string $uri): bool;
}
