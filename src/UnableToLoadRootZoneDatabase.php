<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use Throwable;

class UnableToLoadRootZoneDatabase extends InvalidArgumentException implements ExceptionInterface
{
    public static function dueToInvalidHashMap(): self
    {
        return new self('The decoded hashmap structure is missing at least one of the required properties: `records`, `version` and/or `modifiedDate`.');
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

    public static function dueToInvalidJson(int $code, string $message): self
    {
        return new self('Failed to JSON decode the string: '.$message.'.', $code);
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