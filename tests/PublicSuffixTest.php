<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\Domain;
use Pdp\Exception;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\PublicSuffix
 */
class PublicSuffixTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__debugInfo
     * @covers ::__toString
     * @covers ::jsonSerialize
     * @covers ::getIterator
     */
    public function testInternalPhpMethod()
    {
        $publicSuffix = new PublicSuffix('ac.be');
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        $this->assertEquals($publicSuffix, $generatePublicSuffix);
        $this->assertSame(['be', 'ac'], iterator_to_array($publicSuffix));
        $this->assertJsonStringEqualsJsonString(
            json_encode($publicSuffix->__debugInfo()),
            json_encode($publicSuffix)
        );
        $this->assertSame('ac.be', (string) $publicSuffix);
    }

    /**
     * @covers ::__construct
     * @covers ::setLabels
     * @covers ::setSection
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testPSToUnicodeWithUrlEncode()
    {
        $this->assertSame('bébe', (new PublicSuffix('b%C3%A9be'))->toUnicode()->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::setLabels
     * @covers ::idnToAscii
     */
    public function testPSToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        new PublicSuffix('a⒈com');
    }

    /**
     * @covers ::__construct
     * @covers ::setSection
     */
    public function testSetSectionThrowsException()
    {
        $this->expectException(Exception::class);
        new PublicSuffix('ac.be', 'foobar');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException()
    {
        $this->expectException(Exception::class);
        (new PublicSuffix('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @covers ::toAscii
     * @covers ::toUnicode
     * @covers ::idnToAscii
     * @covers ::idnToUnicode
     */
    public function testConversionReturnsTheSameInstance()
    {
        $instance = new PublicSuffix('ac.be');
        $this->assertSame($instance->toUnicode(), $instance);
        $this->assertSame($instance->toAscii(), $instance);
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeReturnsSameInstance()
    {
        $instance = new PublicSuffix('食狮.公司.cn');
        $this->assertSame($instance->toUnicode(), $instance);
    }

    /**
     * @covers ::count
     * @dataProvider countableProvider
     *
     * @param string|null $domain
     * @param int         $nbLabels
     * @param string[]    $labels
     */
    public function testCountable($domain, $nbLabels, $labels)
    {
        $domain = new PublicSuffix($domain);
        $this->assertCount($nbLabels, $domain);
        $this->assertSame($labels, iterator_to_array($domain));
    }

    public function countableProvider()
    {
        return [
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }

    /**
     * @covers ::getLabel
     */
    public function testGetLabel()
    {
        $domain = new PublicSuffix('master.example.com');
        $this->assertSame('com', $domain->getLabel(0));
        $this->assertSame('example', $domain->getLabel(1));
        $this->assertSame('master', $domain->getLabel(-1));
        $this->assertNull($domain->getLabel(23));
        $this->assertNull($domain->getLabel(-23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $domain = new PublicSuffix('master.example.com');
        $this->assertSame([2], $domain->keys('master'));
    }

    /**
     * @covers ::createFromDomain
     * @dataProvider createFromDomainProvider
     *
     * @param Domain      $domain
     * @param null|string $expected
     */
    public function testCreateFromDomainWorks(Domain $domain, $expected)
    {
        $result = PublicSuffix::createFromDomain($domain);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($result->isKnown(), $domain->isKnown());
        $this->assertSame($result->isICANN(), $domain->isICANN());
        $this->assertSame($result->isPrivate(), $domain->isPrivate());
    }

    public function createFromDomainProvider()
    {
        return [
            [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('be', Rules::ICANN_DOMAINS)),
                'expected' => 'be',
            ],
            [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('bébé.be', Rules::PRIVATE_DOMAINS)),
                'expected' => 'bébé.be',
            ],
            [
                'domain' => new Domain('www.bébé.be'),
                'expected' => null,
            ],
        ];
    }
}
