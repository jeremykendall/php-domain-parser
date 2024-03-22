<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use Throwable;

final class UnableToLoadTopLevelDomainList extends InvalidArgumentException implements CannotProcessHost
{
    public static function dueToInvalidTopLevelDomain(string $content, ?Throwable $exception = null): self
    {
        return new self('Invalid Top Level Domain: '.$content, 0, $exception);
    }

    public static function dueToInvalidVersionLine(string $line): self
    {
        return new self('Invalid Version line: '.$line);
    }

    public static function dueToFailedConversion(): self
    {
        return new self('Invalid content: Top Level Domain List conversion failed.');
    }

    public static function dueToInvalidLine(string $line): self
    {
        return new self('Invalid line content: '.$line);
    }
}
