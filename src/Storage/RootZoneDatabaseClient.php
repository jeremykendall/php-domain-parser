<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\RootZoneDatabase;
use Pdp\UnableToLoadRootZoneDatabase;

interface RootZoneDatabaseClient
{
    /**
     * @throws UnableToLoadRootZoneDatabase
     */
    public function get(string $uri): RootZoneDatabase;
}
