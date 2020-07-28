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

use DateTimeImmutable;
use DateTimeZone;
use Pdp\Domain;
use Pdp\InvalidDomain;
use Pdp\PublicSuffix;
use Pdp\TopLevelDomains;
use Pdp\TopLevelDomainsConverter;
use Pdp\UnableToLoadTopLevelDomains;
use Pdp\UnableToResolveDomain;
use PHPUnit\Framework\TestCase;
use TypeError;
use function file_get_contents;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\TopLevelDomains
 */
class TopLevelDomainsTest extends TestCase
{
    /**
     * @var TopLevelDomains
     */
    protected $collection;

    public function setUp(): void
    {
        $this->collection = TopLevelDomains::createFromPath(__DIR__.'/data/tlds-alpha-by-domain.txt');
    }

    /**
     * @covers ::createFromPath
     * @covers ::createFromString
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

        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/tlds-alpha-by-domain.txt', $context);
        self::assertEquals($this->collection, $collection);
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateFromPathThrowsException(): void
    {
        self::expectException(UnableToLoadTopLevelDomains::class);
        TopLevelDomains::createFromPath('/foo/bar.dat');
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState(): void
    {
        $collection = eval('return '.var_export($this->collection, true).';');
        self::assertEquals($this->collection, $collection);
    }

    public function testGetterProperties(): void
    {
        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/root_zones.dat');
        self::assertCount(15, $collection);
        self::assertSame('2018082200', $collection->getVersion());
        self::assertEquals(
            new DateTimeImmutable('2018-08-22 07:07:01', new DateTimeZone('UTC')),
            $collection->getModifiedDate()
        );
        self::assertFalse($collection->isEmpty());

        $converter = new TopLevelDomainsConverter();
        /** @var string $content */
        $content = file_get_contents(__DIR__.'/data/root_zones.dat');
        $data = $converter->convert($content);
        self::assertEquals($data, $collection->jsonSerialize());

        foreach ($collection as $tld) {
            self::assertInstanceOf(PublicSuffix::class, $tld);
        }
    }

    /**
     * @covers ::getAsciiIDNAOption
     * @covers ::getUnicodeIDNAOption
     * @covers ::withAsciiIDNAOption
     * @covers ::withUnicodeIDNAOption
     */
    public function testwithIDNAOptions(): void
    {
        self::assertSame($this->collection, $this->collection->withAsciiIDNAOption(
            $this->collection->getAsciiIDNAOption()
        ));

        self::assertNotEquals($this->collection, $this->collection->withAsciiIDNAOption(
            128
        ));

        self::assertSame($this->collection, $this->collection->withUnicodeIDNAOption(
            $this->collection->getUnicodeIDNAOption()
        ));

        self::assertNotEquals($this->collection, $this->collection->withUnicodeIDNAOption(
            128
        ));
    }

    /**
     * @dataProvider validDomainProvider
     *
     * @param mixed $tld the tld
     */
    public function testResolve($tld): void
    {
        self::assertSame(
            (new Domain($tld))->label(0),
            $this->collection->resolve($tld)->getPublicSuffix()->getContent()
        );
    }

    public function validDomainProvider(): iterable
    {
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
                public function __toString()
                {
                    return 'www.இந.இந்தியா';
                }
            }],
        ];
    }

    public function testResolveThrowsTypeError(): void
    {
        self::expectException(TypeError::class);
        $this->collection->resolve(new DateTimeImmutable());
    }

    public function testResolveWithInvalidDomain(): void
    {
        self::expectException(InvalidDomain::class);

        $this->collection->resolve('###');
    }

    public function testResolveWithUnResolvableDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        $this->collection->resolve('localhost');
    }

    public function testResolveWithUnregisteredTLD(): void
    {
        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/root_zones.dat');
        self::assertNull($collection->resolve('localhost.locale')->getPublicSuffix()->getContent());
    }

    public function testResolveWithIDNAOptions(): void
    {
        $resolved = $this->collection->resolve('foo.de');
        self::assertSame(
            [IDNA_DEFAULT, IDNA_DEFAULT],
            [$resolved->getAsciiIDNAOption(), $resolved->getUnicodeIDNAOption(),
        ]
        );

        $collection = TopLevelDomains::createFromPath(
            __DIR__.'/data/root_zones.dat',
            null,
            IDNA_NONTRANSITIONAL_TO_ASCII,
            IDNA_NONTRANSITIONAL_TO_UNICODE
        );
        $resolved = $collection->resolve('foo.de');
        self::assertSame(
            [IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE],
            [$resolved->getAsciiIDNAOption(), $resolved->getUnicodeIDNAOption()]
        );
    }

    /**
     * @dataProvider validTldProvider
     *
     * @param mixed $tld the tld
     */
    public function testContainsReturnsTrue($tld): void
    {
        self::assertTrue($this->collection->contains($tld));
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
                public function __toString()
                {
                    return 'COM';
                }
            }],
        ];
    }

    /**
     * @dataProvider invalidTldProvider
     *
     * @param mixed $tld the tld
     */
    public function testContainsReturnsFalse($tld): void
    {
        self::assertFalse($this->collection->contains($tld));
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
                public function __toString()
                {
                    return 'COMMM';
                }
            }],
        ];
    }
}
