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

use Pdp\Converter;
use Pdp\Exception\CouldNotLoadRules;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\Converter
 */
class ConverterTest extends TestCase
{
    public function testConverter()
    {
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');
        $retval = (new Converter())->convert($string);
        self::assertNotEmpty($retval[Converter::ICANN_DOMAINS]);
        self::assertNotEmpty($retval[Converter::PRIVATE_DOMAINS]);
    }

    public function testConvertThrowsExceptionWithInvalidContent()
    {
        self::expectException(CouldNotLoadRules::class);
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');
        (new Converter())->convert($content);
    }

    public function testConvertWithEmptyString()
    {
        $retval = (new Converter())->convert('');
        self::assertEquals([Converter::ICANN_DOMAINS => [], Converter::PRIVATE_DOMAINS => []], $retval);
    }

    public function testConvertWithInvalidString()
    {
        $retval = (new Converter())->convert('foobar');
        self::assertEquals([Converter::ICANN_DOMAINS => [], Converter::PRIVATE_DOMAINS => []], $retval);
    }
}
