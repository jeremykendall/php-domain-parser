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

namespace Pdp;

use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * @coversDefaultClass \Pdp\RootZoneDatabaseConverter
 */
class RootZoneDatabaseConverterTest extends TestCase
{
    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(dirname(__DIR__).'/test_data/root_zones.dat');
        $res = (new RootZoneDatabaseConverter())->convert($string);

        self::assertArrayHasKey('version', $res);
        self::assertArrayHasKey('modifiedDate', $res);
        self::assertArrayHasKey('records', $res);
        self::assertIsArray($res['records']);
    }

    /**
     * @dataProvider invalidContentProvider
     */
    public function testConverterThrowsException(string $content): void
    {
        self::expectException(UnableToLoadRootZoneDatabase::class);

        (new RootZoneDatabaseConverter())->convert($content);
    }

    public function invalidContentProvider(): iterable
    {
        $doubleHeader = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
ABARTH
ABB
ABBOTT
ABBVIE
EOF;

        $invalidHeader = <<<EOF
# Version 2018082200
FOO
BAR
EOF;

        $headerNoFirstLine = <<<EOF
FOO
BAR
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
ABARTH
ABB
ABBOTT
ABBVIE
EOF;

        $invalidTldContent = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
%ef%bc%85%ef%bc%94%ef%bc%91
EOF;

        $invalidTldContentNotRootZone = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
GITHUB.COM
EOF;

        $invalidTldContentEmptyTld = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO

GITHUB.COM
EOF;

        return [
            'double header' => [$doubleHeader],
            'invalid header' => [$invalidHeader],
            'empty content' => [''],
            'header not on the first line' => [$headerNoFirstLine],
            'invalid tld content' => [$invalidTldContent],
            'invalid root zone' => [$invalidTldContentNotRootZone],
            'empty tld' => [$invalidTldContentEmptyTld],
        ];
    }
}
