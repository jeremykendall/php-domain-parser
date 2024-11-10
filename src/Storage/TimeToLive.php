<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;
use Throwable;
use function filter_var;
use const FILTER_VALIDATE_INT;

/**
 * @internal
 */
final class TimeToLive
{
    public static function fromDurationString(string $duration): DateInterval
    {
        try {
            set_error_handler(fn () => true);
            $interval = DateInterval::createFromDateString($duration);
            restore_error_handler();
            if (!$interval instanceof DateInterval) {
                throw new InvalidArgumentException(
                    'The ttl value "'.$duration.'" can not be parsable by `DateInterval::createFromDateString`.'
                );
            }

        } catch (Throwable $exception) {
            if (!$exception instanceof InvalidArgumentException) {
                throw new InvalidArgumentException(
                    'The ttl value "'.$duration.'" can not be parsable by `DateInterval::createFromDateString`.',
                    0,
                    $exception
                );
            }

            throw $exception;
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
        return self::fromDurationString((string) $duration);
    }

    /**
     * Convert the input data into a DateInterval object or null.
     *
     * @throws InvalidArgumentException if the value can not be computed
     */
    public static function convert(DateInterval|DateTimeInterface|Stringable|int|string|null $ttl): ?DateInterval
    {
        if ($ttl instanceof Stringable) {
            $ttl = (string) $ttl;
        }

        return match (true) {
            $ttl instanceof DateInterval || null === $ttl => $ttl,
            $ttl instanceof DateTimeInterface => self::until($ttl),
            is_int($ttl) => self::fromDurationString($ttl.' seconds'),
            false !== ($seconds = filter_var($ttl, FILTER_VALIDATE_INT)) => self::fromDurationString($seconds.' seconds'),
            default => self::fromDurationString($ttl),
        };
    }
}
