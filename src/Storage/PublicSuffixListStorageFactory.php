<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface PublicSuffixListStorageFactory
{
    /**
     * @param mixed $cacheTtl The cache TTL
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage;
}
