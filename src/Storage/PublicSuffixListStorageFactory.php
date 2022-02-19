<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeInterface;
use Stringable;

interface PublicSuffixListStorageFactory
{
    public function createPublicSuffixListStorage(
        string $cachePrefix = '',
        DateInterval|DateTimeInterface|Stringable|int|string|null $cacheTtl = null
    ): PublicSuffixListStorage;
}
