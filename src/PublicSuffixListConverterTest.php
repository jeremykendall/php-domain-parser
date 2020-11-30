<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use TypeError;
use function dirname;
use function file_get_contents;

/**
 * @coversDefaultClass \Pdp\PublicSuffixListConverter
 */
final class PublicSuffixListConverterTest extends TestCase
{
    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(dirname(__DIR__).'/test_data/public_suffix_list.dat');
        $retval = PublicSuffixListConverter::toArray($string);

        self::assertNotEmpty($retval['ICANN_DOMAINS']);
        self::assertNotEmpty($retval['PRIVATE_DOMAINS']);
    }

    public function testConvertThrowsExceptionWithInvalidContent(): void
    {
        /** @var string $content */
        $content = file_get_contents(dirname(__DIR__).'/test_data/invalid_suffix_list_content.dat');

        self::expectException(UnableToLoadPublicSuffixList::class);

        PublicSuffixListConverter::toArray($content);
    }

    public function testConvertWithEmptyString(): void
    {
        $retVal = PublicSuffixListConverter::toArray('');

        self::assertEquals([], $retVal);
    }

    public function testConvertWithInvalidString(): void
    {
        $retVal = PublicSuffixListConverter::toArray('foobar');

        self::assertEquals([], $retVal);
    }

    public function testConvertWithStringableObject(): void
    {
        $stringObject = new class() {
            public function __toString(): string
            {
                return 'foobar';
            }
        };

        $retVal = PublicSuffixListConverter::toArray($stringObject);

        self::assertEquals([], $retVal);
    }

    public function testConvertThrowsExceptionIfTheInputIsNotSupported(): void
    {
        $content = new \stdClass();

        self::expectException(TypeError::class);

        PublicSuffixListConverter::toArray($content);
    }
}
