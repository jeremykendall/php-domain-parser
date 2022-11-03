<?php

declare(strict_types=1);

namespace Pdp;

use function fclose;
use function fopen;
use function stream_get_contents;

/**
 * @internal
 */
final class Stream
{
    /**
     * Returns the content as string from a path.
     *
     * @param null|resource $context
     *
     * @throws UnableToLoadPublicSuffixList If the rules can not be loaded from the path
     */
    public static function getContentAsString(string $path, $context = null): string
    {
        $stream = self::fromPath($path, $context);
        if (false === $stream) {
            throw UnableToLoadResource::dueToInvalidUri($path);
        }

        /** @var string $content */
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * @param null|resource $context
     *
     * @return false|resource
     */
    private static function fromPath(string $path, $context = null)
    {
        $args = [$path, 'r'];
        if (null !== $context) {
            $args = [...$args, false, $context];
        }
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $stream = fopen(...$args);
        restore_error_handler();

        return $stream;
    }
}
