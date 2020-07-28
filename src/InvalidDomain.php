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
use function sprintf;

class InvalidDomain extends InvalidArgumentException implements ExceptionInterface
{
    public static function dueToInvalidLabelKey(int $key): self
    {
        return new self(sprintf('the given key `%s` is invalid', $key));
    }

    public static function dueToUnsupportedLabelType(): self
    {
        return new self('The label can not be NULL');
    }

    public static function dueToInvalidCharacters(string $domain): self
    {
        return new self(sprintf('The host `%s` is invalid: it contains invalid characters', $domain));
    }

    public static function dueToIDNAError(string $domain, string $message = ''): self
    {
        if ('' === $message) {
            return new self(sprintf('The host `%s` is invalid', $domain));
        }

        return new self(sprintf('The host `%s` is invalid : %s', $domain, $message));
    }

    public static function dueToMalformedLabels(string $domain): self
    {
        return new self(sprintf('The domain `%s` is invalid: the labels are malformed', $domain));
    }

    public static function dueToUnsupportedType(string $domain): self
    {
        return new self(sprintf('The domain `%s` is invalid: this is an IPv4 host', $domain));
    }

    public static function dueToInvalidPublicSuffix(string $publicSuffix): self
    {
        return new self(sprintf('The public suffix `%s` is invalid', $publicSuffix));
    }
}
