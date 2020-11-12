<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

class CannotResolveHost extends InvalidArgumentException implements CannotProcessHost
{
    public static function dueToInvalidCharacters(string $domain): self
    {
        return new self('The host `'.$domain.'` is invalid: it contains invalid characters.');
    }

    public static function dueToIDNAError(string $domain, string $message = ''): self
    {
        if ('' === $message) {
            return new self('The host `'.$domain.'` is invalid.');
        }

        return new self('The host `'.$domain.'` is invalid : '.$message);
    }
}
