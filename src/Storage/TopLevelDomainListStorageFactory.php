<?php


declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Stringable;

interface TopLevelDomainListStorageFactory
{
    public function createTopLevelDomainListStorage(
        string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ): TopLevelDomainListStorage;
}
