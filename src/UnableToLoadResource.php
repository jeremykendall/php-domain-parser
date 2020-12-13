<?php

declare(strict_types=1);

namespace Pdp;

use RuntimeException;
use Throwable;

final class UnableToLoadResource extends RuntimeException implements CannotProcessHost
{
    public static function dueToInvalidUri(string $uri): self
    {
        return new self('`'.$uri.'`: failed to open stream; No such URI, file or directory.');
    }

    public static function dueToUnavailableService(string $uri, Throwable $exception): self
    {
        return new self('Could not access the URI: `'.$uri.'`.', 0, $exception);
    }

    public static function dueToUnexpectedStatusCode(string $uri, int $statusCode): self
    {
        return new self('Invalid response from URI: `'.$uri.'`.', $statusCode);
    }
}
