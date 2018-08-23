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
use Pdp\Cache;
use Pdp\Converter;
use Pdp\CurlHttpClient;
use Pdp\Domain;
use Pdp\Exception;
use Pdp\Manager;
use Pdp\PublicSuffix;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @coversDefaultClass Pdp\TopLevelDomains
 */
class TopLevelDomainsTest extends TestCase
{
    protected $collection;

    public function setUp()
    {
        $manager = new Manager(new Cache(), new CurlHttpClient());
        $this->collection = $manager->getTLDs();
    }

    /**
     * @covers ::createFromPath
     * @covers ::createFromString
     * @covers ::__construct
     */
    public function testCreateFromPath()
    {
        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);

        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/root_zones.dat', $context);
        self::assertInstanceOf(TopLevelDomains::class, $collection);
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateFromPathThrowsException()
    {
        self::expectException(Exception::class);
        TopLevelDomains::createFromPath('/foo/bar.dat');
    }

    /**
     * @covers ::createFromArray
     */
    public function testCreateFromArrayThrowsException()
    {
        self::expectException(Exception::class);
        TopLevelDomains::createFromArray(['/foo/bar.dat']);
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState()
    {
        $collection = eval('return '.var_export($this->collection, true).';');
        self::assertEquals($this->collection, $collection);
    }

    public function testGetterProperties()
    {
        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/root_zones.dat');
        self::assertCount(15, $collection);
        self::assertSame('2018082200', $collection->getVersion());
        self::assertEquals(
            new DateTimeImmutable('2018-08-22 07:07:01', new DateTimeZone('UTC')),
            $collection->getLastUpdate()
        );
        self::assertFalse($collection->isEmpty());

        $converter = new Converter();
        $data = $converter->convertRootZoneDatabase(file_get_contents(__DIR__.'/data/root_zones.dat'));
        self::assertEquals($data, $collection->toArray());

        foreach ($collection as $tld) {
            self::assertInstanceOf(PublicSuffix::class, $tld);
        }
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function testResolve($tld)
    {
        self::assertSame(
            (new Domain($tld))->getLabel(0),
            $this->collection->resolve($tld)->getPublicSuffix()
        );
    }

    public function validDomainProvider()
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

    public function testResolveThrowsTypeError()
    {
        self::expectException(TypeError::class);
        $this->collection->resolve(new DateTimeImmutable());
    }

    public function testResolveWithInvalidDomain()
    {
        self::assertEquals(new Domain(), $this->collection->resolve('###'));
    }

    public function testResolveWithUnResolvableDomain()
    {
        $domain = 'localhost';
        self::assertEquals(new Domain($domain), $this->collection->resolve($domain));
    }

    public function testResolveWithUnregisteredTLD()
    {
        $collection = TopLevelDomains::createFromPath(__DIR__.'/data/root_zones.dat');
        self::assertNull($collection->resolve('localhost.locale')->getPublicSuffix());
    }

    /**
     * @dataProvider validTldProvider
     */
    public function testContainsReturnsTrue($tld)
    {
        self::assertTrue($this->collection->contains($tld));
    }

    public function validTldProvider()
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
     */
    public function testContainsReturnsFalse($tld)
    {
        self::assertFalse($this->collection->contains($tld));
    }

    public function invalidTldProvider()
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
            'float' => [1.1],
            'object with __toString method' => [new class() {
                public function __toString()
                {
                    return 'COMMM';
                }
            }],
        ];
    }

    /**
     * @covers \Pdp\Converter::convertRootZoneDatabase
     * @covers \Pdp\Converter::getHeaderInfo
     */
    public function testInvalidFormatToConvertWithMultipleHeader()
    {
        $string = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
ABARTH
ABB
ABBOTT
ABBVIE
EOF;
        self::expectException(Exception::class);
        TopLevelDomains::createFromString($string);
    }

    /**
     * @covers \Pdp\Converter::convertRootZoneDatabase
     * @covers \Pdp\Converter::getHeaderInfo
     */
    public function testInvalidFormatToConvertWithInvalidHeader()
    {
        $string = <<<EOF
# Version 2018082200
FOO
BAR
EOF;
        self::expectException(Exception::class);
        TopLevelDomains::createFromString($string);
    }
}
