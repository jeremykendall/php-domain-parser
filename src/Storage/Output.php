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

namespace Pdp\Storage;

use function fwrite;
use function implode;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

final class Output
{
    /**
     * @param string|string[] $messages
     */
    public function success($messages): void
    {
        fwrite(STDOUT, implode(PHP_EOL, (array) $messages).PHP_EOL);
    }

    /**
     * @param string|string[] $messages
     */
    public function fail($messages): void
    {
        fwrite(STDERR, implode(PHP_EOL, (array) $messages).PHP_EOL);
    }
}
