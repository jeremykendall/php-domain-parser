<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\PublicSuffixListConverter;
use Pdp\UnableToLoadRules;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdp\PublicSuffixListConverter
 */
class PublicSuffixListConverterTest extends TestCase
{
    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');
        $retval = (new PublicSuffixListConverter())->convert($string);

        self::assertNotEmpty($retval['ICANN_DOMAINS']);
        self::assertNotEmpty($retval['PRIVATE_DOMAINS']);
    }

    public function testConvertThrowsExceptionWithInvalidContent(): void
    {
        /** @var string $content */
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');

        self::expectException(UnableToLoadRules::class);

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
