<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface TopLevelDomainListStorage extends TopLevelDomainListClient
{
    public function delete(string $uri): bool;
}
