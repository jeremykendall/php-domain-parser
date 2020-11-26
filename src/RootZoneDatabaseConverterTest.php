<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use TypeError;
use function dirname;
use function file_get_contents;

/**
 * @coversDefaultClass \Pdp\RootZoneDatabaseConverter
 */
final class RootZoneDatabaseConverterTest extends TestCase
{
    private RootZoneDatabaseConverter $converter;

    public function setUp(): void
    {
        $this->converter = new RootZoneDatabaseConverter();
    }

    public function testConverter(): void
    {
        /** @var string $string */
        $string = file_get_contents(dirname(__DIR__).'/test_data/root_zones.dat');
        $res = $this->converter->convert($string);

        self::assertArrayHasKey('version', $res);
        self::assertArrayHasKey('lastUpdated', $res);
        self::assertArrayHasKey('records', $res);
        self::assertIsArray($res['records']);
    }

    public function testConvertWithStringableObject(): void
    {
        $stringObject = new class() {
            public function __toString(): string
            {
                /** @var string $string */
                $string = file_get_contents(dirname(__DIR__).'/test_data/root_zones.dat');

                return $string;
            }
        };

        $res = $this->converter->convert($stringObject);

        self::assertArrayHasKey('version', $res);
        self::assertArrayHasKey('lastUpdated', $res);
        self::assertArrayHasKey('records', $res);
        self::assertIsArray($res['records']);
    }

    /**
     * @dataProvider invalidContentProvider
     */
    public function testConverterThrowsException(string $content): void
    {
        self::expectException(UnableToLoadRootZoneDatabase::class);

        $this->converter->convert($content);
    }

    public function testConvertThrowsExceptionIfTheInputIsNotSupported(): void
    {
        $content = new \stdClass();

        self::expectException(TypeError::class);

        $this->converter->convert($content);
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
