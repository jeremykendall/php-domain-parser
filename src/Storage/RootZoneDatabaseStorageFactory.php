<?php


declare(strict_types=1);

namespace Pdp\Storage;

interface RootZoneDatabaseStorageFactory
{
    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createRootZoneDatabaseStorage(string $cachePrefix = '', $cacheTtl = null): RootZoneDatabaseClient;
}
