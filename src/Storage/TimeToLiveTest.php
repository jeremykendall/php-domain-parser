<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

final class TimeToLiveTest extends TestCase
{
    public function testItDoesNotReturnTheAbsoluteInterval(): void
    {
        $yesterday = new DateTimeImmutable('yesterday', new DateTimeZone('Africa/Kigali'));
        $tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Tokyo'));

        self::assertSame(1, TimeToLive::until($yesterday)->invert);
        self::assertSame(0, TimeToLive::until($tomorrow)->invert);
    }

    #[DataProvider('validDurationString')]
    public function testItCanBeInstantiatedFromDurationInput(string $input, DateInterval $expected): void
    {
        $now = new DateTimeImmutable();

        self::assertEquals($now->add($expected), $now->add(TimeToLive::fromDurationString($input)));
    }

    /**
     * @return iterable<string, array{input:string, expected:DateInterval}>
     */
    public static function validDurationString(): iterable
    {
        $threeDays = new DateInterval('P3D');

        yield 'string' => [
            'input' => '3 days',
            'expected' => $threeDays,
        ];

        yield 'stringable negative days' => [
            'input' => '-3 days',
            'expected' => DateInterval::createFromDateString('-3 DAYS'),
        ];
    }

    public function testItCastToNullWithNull(): void
    {
        self::assertNull(TimeToLive::convert(null));
    }

    #[DataProvider('validDurationInt')]
    public function testItCanBeInstantiatedFromSeconds(int|string|Stringable|DateInterval $input, DateInterval $expected): void
    {
        /** @var DateInterval $ttl */
        $ttl = TimeToLive::convert($input);
        $now = new DateTimeImmutable();

        self::assertEquals($now->add($expected), $now->add($ttl));
    }

    /**
     * @return iterable<string, array{input:int|string|object|DateInterval, expected:DateInterval|null}>
     */
    public static function validDurationInt(): iterable
    {
        $seconds = new DateInterval('PT2345S');

        yield 'DateInterval' => [
            'input' => $seconds,
            'expected' => $seconds,
        ];

        yield 'stringable object' => [
            'input' => new class() {
                public function __toString(): string
                {
                    return '2345';
                }
            },
            'expected' => $seconds,
        ];

        yield 'numeric string' => [
            'input' => '2345',
            'expected' => $seconds,
        ];

        yield 'seconds' => [
            'input' => 2345,
            'expected' => $seconds,
        ];

        yield 'negative seconds' => [
            'input' => '-2345',
            'expected' => DateInterval::createFromDateString('-2345 seconds'),
        ];
    }
}
