<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
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

    /**
     * @dataProvider validDurationString
     */
    public function testItCanBeInstantiatedFromDurationInput(string $input, DateInterval $expected): void
    {
        self::assertEquals($expected, TimeToLive::fromDurationString($input));
    }

    /**
     * @return iterable<string, array{input:string, expected:DateInterval}>
     */
    public function validDurationString(): iterable
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

    /**
     * @dataProvider validDurationInt
     */
    public function testItCanBeInstantiatedFromSeconds(
        DateInterval|Stringable|int|string|null $input,
        DateInterval|null $expected
    ): void {
        self::assertEquals($expected, TimeToLive::convert($input));
    }

    /**
     * @return iterable<string, array{input:DateInterval|Stringable|int|string|null, expected:DateInterval|null}>
     */
    public function validDurationInt(): iterable
    {
        $seconds = new DateInterval('PT2345S');

        yield 'DateInterval' => [
            'input' => $seconds,
            'expected' => $seconds,
        ];

        yield 'null' => [
            'input' => null,
            'expected' => null,
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
