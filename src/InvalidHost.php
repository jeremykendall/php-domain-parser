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

class InvalidHost extends InvalidArgumentException implements ExceptionInterface
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
