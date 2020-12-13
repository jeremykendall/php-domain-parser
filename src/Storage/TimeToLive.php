<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use TypeError;
use function filter_var;
use function is_object;
use function is_string;
use function method_exists;
use const FILTER_VALIDATE_INT;

final class TimeToLive
{
    public static function fromDateTimeInterface(DateTimeInterface $ttl): DateInterval
    {
        /** @var DateTimeZone $timezone */
        $timezone = $ttl->getTimezone();

        $now = new DateTimeImmutable('NOW', $timezone);

        /** @var DateInterval $diff */
        $diff = $now->diff($ttl, false);

        return $diff;
    }

    /**
     * @param object|string|int $ttl the cache TTL the object must implement the __toString method
     */
    public static function fromScalar($ttl): DateInterval
    {
        if (is_object($ttl) && method_exists($ttl, '__toString')) {
            $ttl = (string) $ttl;
        }

        if (false !== ($res = filter_var($ttl, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        if (!is_string($ttl)) {
            throw new TypeError('The ttl must null, an integer, a string, a DateTimeInterface or a DateInterval object.');
        }

        /** @var DateInterval|false $date */
        $date = @DateInterval::createFromDateString($ttl);
        if (!$date instanceof DateInterval) {
            throw new InvalidArgumentException(
                'The ttl value "'.$ttl.'" can not be parsable by `DateInterval::createFromDateString`.'
            );
        }

        return $date;
    }

    /**
     * Set the cache TTL.
     *
     * @param mixed $ttl the cache TTL
     *
     * @throws InvalidArgumentException if the value can not be computed
     * @throws TypeError                if the value type is not recognized
     */
    public static function convert($ttl): ?DateInterval
    {
        if ($ttl instanceof DateInterval || null === $ttl) {
            return $ttl;
        }

        if ($ttl instanceof DateTimeInterface) {
            return self::fromDateTimeInterface($ttl);
        }

        return self::fromScalar($ttl);
    }
}
