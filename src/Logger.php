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

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use function fwrite;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

final class Logger extends AbstractLogger
{
    const ERRORS_LEVELS = [
        LogLevel::EMERGENCY => 1,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 1,
        LogLevel::ERROR => 1,
        LogLevel::WARNING => 1,
        LogLevel::NOTICE => 1,
    ];

    /**
     * @var resource
     */
    private $out;

    /**
     * @var resource
     */
    private $error;

    public function __construct($out = STDOUT, $error = STDERR)
    {
        $this->out = $out;
        $this->error = $error;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{'.$key.'}'] = $val;
        }

        fwrite(
            isset(self::ERRORS_LEVELS[$level]) ? $this->error : $this->out,
            strtr($message, $replace).PHP_EOL
        );
    }
}
