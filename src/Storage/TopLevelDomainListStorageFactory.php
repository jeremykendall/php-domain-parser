<?php


declare(strict_types=1);

namespace Pdp\Storage;

interface TopLevelDomainListStorageFactory
{
    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createTopLevelDomainListStorage(string $cachePrefix = '', $cacheTtl = null): TopLevelDomainListStorage;
}
