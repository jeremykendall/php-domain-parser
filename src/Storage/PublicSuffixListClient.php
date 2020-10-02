<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\PublicSuffixList;
use Pdp\UnableToLoadPublicSuffixList;

interface PublicSuffixListClient
{
    /**
     * @throws UnableToLoadPublicSuffixList
     */
    public function get(string $uri): PublicSuffixList;
}
