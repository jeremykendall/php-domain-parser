<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface RootZoneDatabaseStorage extends RootZoneDatabaseClient
{
    public function delete(string $uri): bool;
}
