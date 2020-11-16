<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use Throwable;

class UnableToLoadPublicSuffixList extends InvalidArgumentException implements CannotProcessHost
{
    public static function dueToInvalidPath(string $path): self
    {
        return new self($path.': failed to open stream: No such file or directory.');
    }

    public static function dueToInvalidJson(Throwable $exception): self
    {
        return new self('Failed to JSON decode public suffix list string.', 0, $exception);
    }

    public static function dueToInvalidRule(?string $line, Throwable $exception): self
    {
        return new self('The following rule "'.$line ?? 'NULL'.'" could not be processed because it is invalid.', 0, $exception);
    }

    public static function dueToCorruptedSection(): self
    {
        return new self('The public suffix list section data are corrupted.');
    }

    public static function dueToUnavailableService(string $uri, Throwable $exception): self
    {
        return new self('Could not access the Public Suffix List URI: `'.$uri.'`.', 0, $exception);
    }

    public static function dueToUnexpectedContent(string $uri, int $statusCode): self
    {
        return new self('Invalid response from Public Suffix List URI: `'.$uri.'`.', $statusCode);
    }
}
