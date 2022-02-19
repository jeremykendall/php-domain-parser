<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;

interface TopLevelDomainListStorageFactory
{
    public function createTopLevelDomainListStorage(
        string $cachePrefix = '',
        DateInterval|int|null $cacheTtl = null
    ): TopLevelDomainListStorage;
}
