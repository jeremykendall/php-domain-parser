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
        $resource = self::getResource($path, $context);
        if (false === $resource) {
            throw UnableToLoadResource::dueToInvalidUri($path);
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        return $content;
    }

    /**
     * @param null|resource $context
     *
     * @return false|resource
     */
    private static function getResource(string $path, $context = null)
    {
        if (null === $context) {
            return @fopen($path, 'r');
        }

        return @fopen($path, 'r', false, $context);
    }
}
