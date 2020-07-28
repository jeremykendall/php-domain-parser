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

use Pdp\RulesConverter;
use Pdp\UnableToLoadRules;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdp\RulesConverter
 */
class ConverterTest extends TestCase
{
    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');
        $retval = (new RulesConverter())->convert($string);

        self::assertNotEmpty($retval['ICANN_DOMAINS']);
        self::assertNotEmpty($retval['PRIVATE_DOMAINS']);
    }

    public function testConvertThrowsExceptionWithInvalidContent(): void
    {
        /** @var string $content */
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');

        self::expectException(UnableToLoadRules::class);

        (new RulesConverter())->convert($content);
    }

    public function testConvertWithEmptyString(): void
    {
        $retVal = (new RulesConverter())->convert('');

        self::assertEquals(['ICANN_DOMAINS' => [], 'PRIVATE_DOMAINS' => []], $retVal);
    }

    public function testConvertWithInvalidString(): void
    {
        $retVal = (new RulesConverter())->convert('foobar');

        self::assertEquals(['ICANN_DOMAINS' => [], 'PRIVATE_DOMAINS' => []], $retVal);
    }
}
