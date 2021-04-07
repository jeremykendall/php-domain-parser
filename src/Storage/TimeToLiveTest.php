<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class TimeToLiveTest extends TestCase
{
    public function testItDoesNotReturnTheAbsoluteInterval(): void
    {
        $yesterday = new DateTimeImmutable('yesterday', new DateTimeZone('Africa/Kigali'));
        $tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Tokyo'));

        self::assertSame(1, TimeToLive::until($yesterday)->invert);
        self::assertSame(0, TimeToLive::until($tomorrow)->invert);
    }

    /**
     * @param object|string|int $input
     * @dataProvider validDurationString
     */
    public function testItCanBeInstantiatedFromDurationInput($input, DateInterval $expected): void
    {
        self::assertEquals($expected, TimeToLive::fromDurationString($input));
    }

    /**
     * @return iterable<string, array{input:int|string|object, expected:DateInterval}>
     */
    public function validDurationString(): iterable
    {
        $threeDays = new DateInterval('P3D');

        yield 'stringable object' => [
            'input' => new class() {
                public function __toString(): string
                {
                    return '3 days';
                }
            },
            'expected' => $threeDays,
        ];

        yield 'string' => [
            'input' => '3 days',
            'expected' => $threeDays,
        ];

        $seconds = new DateInterval('PT2345S');

        yield 'stringable seconds' => [
            'input' => '2345',
            'expected' => $seconds,
        ];

        yield 'seconds' => [
            'input' => 2345,
            'expected' => $seconds,
        ];

        $negativeInterval = clone $seconds;
        $negativeInterval->invert = 1;

        yield 'negative seconds' => [
            'input' => '-2345',
            'expected' => $negativeInterval,
        ];
    }
}
