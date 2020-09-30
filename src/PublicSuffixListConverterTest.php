<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * @coversDefaultClass \Pdp\PublicSuffixListConverter
 */
class PublicSuffixListConverterTest extends TestCase
{
    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(dirname(__DIR__).'/test_data/public_suffix_list.dat');
        $retval = (new PublicSuffixListConverter())->convert($string);

        self::assertNotEmpty($retval['ICANN_DOMAINS']);
        self::assertNotEmpty($retval['PRIVATE_DOMAINS']);
    }

    public function testConvertThrowsExceptionWithInvalidContent(): void
    {
        /** @var string $content */
        $content = file_get_contents(dirname(__DIR__).'/test_data/invalid_suffix_list_content.dat');

        self::expectException(UnableToLoadPublicSuffixList::class);

        (new PublicSuffixListConverter())->convert($content);
    }

    public function testConvertWithEmptyString(): void
    {
        $retVal = (new PublicSuffixListConverter())->convert('');

        self::assertEquals(['ICANN_DOMAINS' => [], 'PRIVATE_DOMAINS' => []], $retVal);
    }

    public function testConvertWithInvalidString(): void
    {
        $retVal = (new PublicSuffixListConverter())->convert('foobar');

        self::assertEquals(['ICANN_DOMAINS' => [], 'PRIVATE_DOMAINS' => []], $retVal);
    }
}
