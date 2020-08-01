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

class UnableToLoadPublicSuffixList extends InvalidArgumentException implements ExceptionInterface
{
    public static function dueToInvalidPath(string $path): self
    {
        return new self($path.': failed to open stream: No such file or directory.');
    }

    public static function dueToInvalidJson(int $code, string $message): self
    {
        return new self($message, $code);
    }

    public static function dueToInvalidRule(?string $line, Throwable $exception): self
    {
        return new self('The following rule "'.$line ?? 'NULL'.'" could not be processed because it is invalid.', 0, $exception);
    }

    public static function dueToInvalidHashMap(): self
    {
        return new self('The public suffix list data are missing one of the required ICANN or PRIVATE domain section.');
    }

    public static function dueToCorruptedSection(): self
    {
        return new self('The public suffix list section data are corrupted.');
    }
}
