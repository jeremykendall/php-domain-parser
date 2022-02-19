<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;

interface PublicSuffixListStorageFactory
{
    public function createPublicSuffixListStorage(
        string $cachePrefix = '',
        DateInterval|int|null $cacheTtl = null
    ): PublicSuffixListStorage;
}
