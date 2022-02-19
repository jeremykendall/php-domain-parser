<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;
use TypeError;
use function filter_var;
use const FILTER_VALIDATE_INT;

/**
 * @internal
 */
final class TimeToLive
{
    public static function fromDurationString(string $duration): DateInterval
    {
        /** @var DateInterval|false $interval */
        $interval = @DateInterval::createFromDateString($duration);
        if (!$interval instanceof DateInterval) {
            throw new InvalidArgumentException(
                'The ttl value "'.$duration.'" can not be parsable by `DateInterval::createFromDateString`.'
            );
        }

        return $interval;
    }

    public static function until(DateTimeInterface $date): DateInterval
    {
        return (new DateTimeImmutable('NOW', $date->getTimezone()))->diff($date, false);
    }

    /**
     * Returns a DateInterval relative to the current date and time.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 6.1.0 deprecated
     * @codeCoverageIgnore
     * @see TimeToLive::until
     */
    public static function fromDateTimeInterface(DateTimeInterface $date): DateInterval
    {
        return self::until($date);
    }

    /**
     * Returns a DateInterval from string parsing.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 6.1.0 deprecated
     * @see TimeToLive::fromDurationString
     * @codeCoverageIgnore
     *
     * @throws InvalidArgumentException if the value can not be parsable
     *
     */
    public static function fromScalar(Stringable|int|string $duration): DateInterval
    {
        if ($duration instanceof Stringable) {
            $duration = (string) $duration;
        }

        return self::fromDurationString((string) $duration);
    }

    /**
     * Convert the input data into a DateInterval object or null.
     *
     * @throws InvalidArgumentException if the value can not be computed
     * @throws TypeError                if the value type is not recognized
     */
    public static function convert(DateInterval|DateTimeInterface|Stringable|int|string|null $ttl): DateInterval|null
    {
        if ($ttl instanceof DateInterval || null === $ttl) {
            return $ttl;
        }

        if ($ttl instanceof DateTimeInterface) {
            return self::until($ttl);
        }

        if ($ttl instanceof Stringable) {
            $ttl = (string) $ttl;
        }

        if (false !== ($seconds = filter_var($ttl, FILTER_VALIDATE_INT))) {
            return self::fromDurationString($seconds.' seconds');
        }

        if (!is_string($ttl)) {
            throw new TypeError('The duration type is unsupported or is an non stringable object.');
        }

        return self::fromDurationString($ttl);
    }
}
