<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface PublicSuffixListStorage extends PublicSuffixListClient
{
    public function delete(string $uri): bool;
}
