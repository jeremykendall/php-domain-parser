<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;

interface PublicSuffixListStorageFactory
{
    /**
     * @param DateInterval|DateTimeInterface|object|int|string|null $cacheTtl storage TTL object should implement the __toString method
     */
    public function createPublicSuffixListStorage(string $cachePrefix = '', $cacheTtl = null): PublicSuffixListStorage;
}
