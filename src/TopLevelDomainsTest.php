<?php

declare(strict_types=1);

namespace Pdp;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TypeError;
use function dirname;
use function file_get_contents;
use function json_encode;

/**
 * @coversDefaultClass \Pdp\TopLevelDomains
 */
final class TopLevelDomainsTest extends TestCase
{
    protected TopLevelDomains $topLevelDomains;

    public function setUp(): void
    {
        $this->topLevelDomains = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/tlds-alpha-by-domain.txt');
    }

    /**
     * @covers ::fromPath
     * @covers ::fromString
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

        self::assertEquals($this->topLevelDomains, $topLevelDomains);
    }

    /**
     * @covers ::fromPath
     * @covers \Pdp\UnableToLoadRootZoneDatabase
     */
    public function testCreateFromPathThrowsException(): void
    {
        self::expectException(UnableToLoadRootZoneDatabase::class);

        TopLevelDomains::fromPath('/foo/bar.dat');
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState(): void
    {
        $topLevelDomains = eval('return '.var_export($this->topLevelDomains, true).';');

        self::assertEquals($this->topLevelDomains, $topLevelDomains);
    }

    /**
     * @covers ::jsonSerialize
     * @covers ::fromJsonString
     */
    public function testJsonMethods(): void
    {
        /** @var string $data */
        $data = json_encode($this->topLevelDomains);

        self::assertEquals($this->topLevelDomains, TopLevelDomains::fromJsonString($data));
    }

    /**
     * @covers ::fromJsonString
     * @covers \Pdp\UnableToLoadRootZoneDatabase
     */
    public function testJsonStringFailsWithInvalidJson(): void
    {
        self::expectException(UnableToLoadRootZoneDatabase::class);

        TopLevelDomains::fromJsonString('');
    }

    /**
     * @covers ::fromJsonString
     * @covers \Pdp\UnableToLoadRootZoneDatabase
     */
    public function testJsonStringFailsWithMissingIndexes(): void
    {
        self::expectException(UnableToLoadRootZoneDatabase::class);

        TopLevelDomains::fromJsonString('{"foo":"bar"}');
    }

    public function testGetterProperties(): void
    {
        $topLevelDomains = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/root_zones.dat');

        self::assertCount(15, $topLevelDomains);
        self::assertSame('2018082200', $topLevelDomains->getVersion());
        self::assertEquals(
            new DateTimeImmutable('2018-08-22 07:07:01', new DateTimeZone('UTC')),
            $topLevelDomains->getModifiedDate()
        );
        self::assertFalse($topLevelDomains->isEmpty());

        $converter = new RootZoneDatabaseConverter();
        /** @var string $content */
        $content = file_get_contents(dirname(__DIR__).'/test_data/root_zones.dat');
        $data = $converter->convert($content);
        self::assertEquals($data, $topLevelDomains->jsonSerialize());

        foreach ($topLevelDomains as $tld) {
            self::assertInstanceOf(PublicSuffix::class, $tld);
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
            $this->topLevelDomains->resolve($tld)->publicSuffix()->value()
        );
    }

    public function validDomainProvider(): iterable
    {
        $resolvedDomain = new ResolvedDomain(
            Domain::fromIDNA2008('www.example.com'),
            PublicSuffix::fromICANN(Domain::fromIDNA2008('com'))
        );

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

    public function testResolveThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        $this->topLevelDomains->resolve(new DateTimeImmutable());
    }

    public function testResolveWithInvalidDomain(): void
    {
        self::expectException(SyntaxError::class);

        $this->topLevelDomains->resolve('###');
    }

    public function testResolveWithUnResolvableDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        $this->topLevelDomains->resolve('localhost');
    }

    public function testResolveWithUnregisteredTLD(): void
    {
        $collection = TopLevelDomains::fromPath(dirname(__DIR__).'/test_data/root_zones.dat');

        self::assertNull($collection->resolve('localhost.locale')->publicSuffix()->value());
    }

    public function testResolveWithIDNAOptions(): void
    {
        $resolved = $this->topLevelDomains->resolve('foo.de');

        self::assertTrue($resolved->domain()->isIdna2008());

        $collection = TopLevelDomains::fromPath(
            dirname(__DIR__).'/test_data/root_zones.dat',
            null
        );

        $domain = Domain::fromIDNA2003('foo.de');
        $resolved = $collection->resolve($domain);

        self::assertFalse($resolved->domain()->isIdna2008());
    }

    /**
     * @dataProvider validTldProvider
     *
     * @param mixed $tld the tld
     */
    public function testContainsReturnsTrue($tld): void
    {
        self::assertTrue($this->topLevelDomains->contains($tld));
    }

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
            'externalDomain' => [PublicSuffix::fromICANN(Domain::fromIDNA2008('com'))],
        ];
    }

    /**
     * @dataProvider invalidTldProvider
     *
     * @param mixed $tld the tld
     */
    public function testContainsReturnsFalse($tld): void
    {
        self::assertFalse($this->topLevelDomains->contains($tld));
    }

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
