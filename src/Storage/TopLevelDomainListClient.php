<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;
use Pdp\UnableToLoadTopLevelDomainList;

interface TopLevelDomainListClient
{
    /**
     * @throws UnableToLoadTopLevelDomainList
     */
    public function get(string $uri): TopLevelDomainList;
}
