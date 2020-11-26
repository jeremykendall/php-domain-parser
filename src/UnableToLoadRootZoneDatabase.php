<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use Throwable;

final class UnableToLoadRootZoneDatabase extends InvalidArgumentException implements CannotProcessHost
{
    public static function dueToInvalidHashMap(): self
    {
        return new self('The decoded hashmap structure is missing at least one of the required properties: `records`, `version` and/or `lastUpdated`.');
    }

    public static function dueToInvalidRootZoneDomain(string $content, Throwable $exception = null): self
    {
        return new self('Invalid Root zone: '.$content, 0, $exception);
    }

    public static function dueToInvalidVersionLine(string $line): self
    {
        return new self('Invalid Version line: '.$line);
    }

    public static function dueToFailedConversion(): self
    {
        return new self('Invalid content: TLD conversion failed.');
    }

    public static function dueToInvalidLine(string $line): self
    {
        return new self('Invalid line content: '.$line);
    }

    public static function dueToInvalidPath(string $path): self
    {
        return new self($path.': failed to open stream: No such file or directory.');
    }

    public static function dueToInvalidJson(Throwable $exception): self
    {
        return new self('Failed to JSON decode the Root Zone Database string.', 0, $exception);
    }

    public static function dueToUnavailableService(string $uri, Throwable $exception): self
    {
        return new self('Could not access the Root Zone Database URI: `'.$uri.'`.', 0, $exception);
    }

    public static function dueToUnexpectedContent(string $uri, int $statusCode): self
    {
        return new self('Invalid response from Root Zone Database URI: `'.$uri.'`.', $statusCode);
    }
}
