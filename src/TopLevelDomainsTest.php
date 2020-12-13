<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TypeError;
use function dirname;

/**
 * @coversDefaultClass \Pdp\TopLevelDomains
 */
final class TopLevelDomainsTest extends TestCase
{
    private static TopLevelDomains $topLevelDomains;

    public static function setUpBeforeClass(): void
    {
        self::$topLevelDomains = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/tlds-alpha-by-domain.txt');
    }

    /**
     * @covers ::fromPath
     * @covers ::fromString
     * @covers \Pdp\Stream
     * @covers ::__construct
     */
    public function testCreateFromPath(): void
    {
        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);

        $topLevelDomains = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/tlds-alpha-by-domain.txt', $context);

        self::assertEquals(self::$topLevelDomains, $topLevelDomains);
    }

    /**
     * @covers ::fromPath
     * @covers \Pdp\UnableToLoadTopLevelDomainList
     */
    public function testCreateFromPathThrowsException(): void
    {
        $this->expectException(UnableToLoadResource::class);

        TopLevelDomains::fromPath('/foo/bar.dat');
    }

    public function testFromStringThrowsOnTypeError(): void
    {
        $this->expectException(TypeError::class);

        TopLevelDomains::fromString(new DateTimeImmutable());
    }

    /**
     * @dataProvider invalidContentProvider
     */
    public function testConverterThrowsException(string $content): void
    {
        $this->expectException(UnableToLoadTopLevelDomainList::class);

        TopLevelDomains::fromString($content);
    }

    /**
     * @return iterable<string,array{0:string}>
     */
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

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState(): void
    {
        $topLevelDomains = eval('return '.var_export(self::$topLevelDomains, true).';');

        self::assertEquals(self::$topLevelDomains, $topLevelDomains);
    }

    public function testGetterProperties(): void
    {
        $topLevelDomains = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/root_zones.dat');

        self::assertCount(15, $topLevelDomains);
        self::assertSame('2018082200', $topLevelDomains->version());
        self::assertEquals(
            new DateTimeImmutable('2018-08-22 07:07:01', new DateTimeZone('UTC')),
            $topLevelDomains->lastUpdated()
        );
        self::assertFalse($topLevelDomains->isEmpty());
    }

    public function testIterator(): void
    {
        foreach (self::$topLevelDomains as $tld) {
            self::assertStringNotContainsString($tld, '.');
        }
    }

    /**
     * @dataProvider validDomainProvider
     *
     * @param mixed $tld the tld
     */
    public function testResolve($tld): void
    {
        self::assertSame(
            Domain::fromIDNA2008($tld)->label(0),
            self::$topLevelDomains->resolve($tld)->suffix()->value()
        );
    }

    /**
     * @dataProvider validDomainProvider
     *
     * @param mixed $tld the tld
     */
    public function testGetTopLevelDomain($tld): void
    {
        self::assertSame(
            Domain::fromIDNA2008($tld)->label(0),
            self::$topLevelDomains->getIANADomain($tld)->suffix()->value()
        );
    }

    /**
     * @return iterable<string,array>
     */
    public function validDomainProvider(): iterable
    {
        $resolvedDomain = ResolvedDomain::fromICANN(Domain::fromIDNA2008('www.example.com'), 1);

        return [
            'simple domain' => ['GOOGLE.COM'],
            'case insensitive domain (1)' => ['GooGlE.com'],
            'case insensitive domain (2)' => ['gooGle.coM'],
            'case insensitive domain (3)' => ['GooGLE.CoM'],
            'IDN to ASCII domain' => ['GOOGLE.XN--VERMGENSBERATUNG-PWB'],
            'Unicode domain (1)' => ['الاعلى-للاتصالات.قطر'],
            'Unicode domain (2)' => ['кто.рф'],
            'Unicode domain (3)' => ['Deutsche.Vermögensberatung.vermögensberater'],
            'object with __toString method' => [new class() {
                public function __toString(): string
                {
                    return 'www.இந.இந்தியா';
                }
            }],
            'external domain name' => [$resolvedDomain],
        ];
    }

    public function testTopLevelDomainThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        self::$topLevelDomains->getIANADomain(new DateTimeImmutable());
    }

    public function testTopLevelDomainWithInvalidDomain(): void
    {
        $this->expectException(SyntaxError::class);

        self::$topLevelDomains->getIANADomain('###');
    }

    public function testResolveWithInvalidDomain(): void
    {
        $result = self::$topLevelDomains->resolve('###');
        self::assertFalse($result->suffix()->isIANA());
        self::assertNull($result->value());
    }

    public function testResolveWithAbsoluteDomainName(): void
    {
        $result = self::$topLevelDomains->resolve('example.com.');
        self::assertSame('example.com.', $result->value());
        self::assertFalse($result->suffix()->isIANA());
        self::assertNull($result->suffix()->value());
    }

    public function testTopLevelDomainWithUnResolvableDomain(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        self::$topLevelDomains->getIANADomain('localhost');
    }

    public function testResolveWithUnResolvableDomain(): void
    {
        $result = self::$topLevelDomains->resolve('localhost');

        self::assertSame($result->toString(), 'localhost');
        self::assertNull($result->suffix()->value());
        self::assertFalse($result->suffix()->isIANA());
    }

    public function testGetTopLevelDomainWithUnResolvableDomain(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        self::$topLevelDomains->getIANADomain('localhost');
    }

    public function testResolveWithUnregisteredTLD(): void
    {
        $collection = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/root_zones.dat');

        self::assertNull($collection->resolve('localhost.locale')->suffix()->value());
    }

    public function testGetTopLevelDomainWithUnregisteredTLD(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        $collection = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/root_zones.dat');
        $collection->getIANADomain('localhost.locale');
    }

    /**
     * @return iterable<string,array>
     */
    public function validTldProvider(): iterable
    {
        return [
            'simple TLD' => ['COM'],
            'case insenstive detection (1)' => ['cOm'],
            'case insenstive detection (2)' => ['CoM'],
            'case insenstive detection (3)' => ['com'],
            'IDN to ASCI TLD' => ['XN--CLCHC0EA0B2G2A9GCD'],
            'Unicode TLD (1)' => ['المغرب'],
            'Unicode TLD (2)' => ['مليسيا'],
            'Unicode TLD (3)' => ['рф'],
            'Unicode TLD (4)' => ['இந்தியா'],
            'Unicode TLD (5)' => ['vermögensberater'],
            'object with __toString method' => [new class() {
                public function __toString(): string
                {
                    return 'COM';
                }
            }],
            'externalDomain' => [Suffix::fromICANN('com')],
        ];
    }

    /**
     * @return iterable<string,array>
     */
    public function invalidTldProvider(): iterable
    {
        return [
            'invalid TLD (1)' => ['COMM'],
            'invalid TLD with leading dot' => ['.CCOM'],
            'invalid TLD case insensitive' => ['cCoM'],
            'invalid TLD case insensitive with leading dot' => ['.cCoM'],
            'invalid TLD (2)' => ['BLABLA'],
            'invalid TLD (3)' => ['CO M'],
            'invalid TLD (4)' => ['D.E'],
            'invalid Unicode TLD' => ['CÖM'],
            'invalid IDN to ASCII' => ['XN--TTT'],
            'invalid IDN to ASCII with leading dot' => ['.XN--TTT'],
            'null' => [null],
            'object with __toString method' => [new class() {
                public function __toString(): string
                {
                    return 'COMMM';
                }
            }],
        ];
    }
}
