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
class PublicSuffixListConverterTest extends TestCase
{
    private PublicSuffixListConverter $converter;

    public function setUp(): void
    {
        $this->converter = new PublicSuffixListConverter();
    }

    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(dirname(__DIR__).'/test_data/public_suffix_list.dat');
        $retval = $this->converter->convert($string);

        self::assertNotEmpty($retval['ICANN_DOMAINS']);
        self::assertNotEmpty($retval['PRIVATE_DOMAINS']);
    }

    public function testConvertThrowsExceptionWithInvalidContent(): void
    {
        /** @var string $content */
        $content = file_get_contents(dirname(__DIR__).'/test_data/invalid_suffix_list_content.dat');

        self::expectException(UnableToLoadPublicSuffixList::class);

        $this->converter->convert($content);
    }

    public function testConvertWithEmptyString(): void
    {
        $retVal = $this->converter->convert('');

        self::assertEquals([], $retVal);
    }

    public function testConvertWithInvalidString(): void
    {
        $retVal = $this->converter->convert('foobar');

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

        $retVal = $this->converter->convert($stringObject);

        self::assertEquals([], $retVal);
    }

    public function testConvertThrowsExceptionIfTheInputIsNotSupported(): void
    {
        $content = new \stdClass();

        self::expectException(TypeError::class);

        $this->converter->convert($content);
    }
}
