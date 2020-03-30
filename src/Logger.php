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
use TypeError;
use function fwrite;
use function is_resource;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

final class Logger extends AbstractLogger
{
    private const ERRORS_LEVELS = [
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

    /**
     * @param mixed $out   a resource default to PHP STDOUT
     * @param mixed $error a resource default to PHP STDERR
     */
    public function __construct($out = STDOUT, $error = STDERR)
    {
        if (!is_resource($out)) {
            throw new TypeError('The output logger should be a resource.');
        }

        if (!is_resource($error)) {
            throw new TypeError('The error output logger channel should be a resource.');
        }

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
