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

use Pdp\Domain;
use Pdp\Exception\CouldNotResolvePublicSuffix;
use Pdp\Exception\InvalidDomain;
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
     * @covers ::setPublicSuffix
     * @covers ::setSection
     * @covers ::isKnown
     * @covers ::isICANN
     * @covers ::isPrivate
     * @dataProvider PSProvider
     *
     * @param string|null $publicSuffix
     * @param string      $section
     * @param bool        $isKnown
     * @param bool        $isIcann
     * @param bool        $isPrivate
     */
    public function testSetSection($publicSuffix, string $section, bool $isKnown, bool $isIcann, bool $isPrivate)
    {
        $ps = new PublicSuffix($publicSuffix, $section);
        $this->assertSame($isKnown, $ps->isKnown());
        $this->assertSame($isIcann, $ps->isICANN());
        $this->assertSame($isPrivate, $ps->isPrivate());
    }

    public function PSProvider()
    {
        return [
            [null, PublicSuffix::ICANN_DOMAINS, false, false, false],
            ['foo', PublicSuffix::ICANN_DOMAINS, true, true, false],
            ['foo', PublicSuffix::PRIVATE_DOMAINS, true, false, true],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::setLabels
     * @covers ::setPublicSuffix
     * @dataProvider invalidPublicSuffixProvider
     *
     * @param mixed $publicSuffix
     */
    public function testConstructorThrowsException($publicSuffix)
    {
        $this->expectException(InvalidDomain::class);
        new PublicSuffix($publicSuffix);
    }

    public function invalidPublicSuffixProvider()
    {
        return [
            'empty string' => [''],
            'absolute host' => ['foo.'],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::setLabels
     * @covers ::idnToAscii
     */
    public function testPSToAsciiThrowsException()
    {
        $this->expectException(InvalidDomain::class);
        new PublicSuffix('a⒈com');
    }

    /**
     * @covers ::__construct
     * @covers ::setSection
     */
    public function testSetSectionThrowsException()
    {
        $this->expectException(CouldNotResolvePublicSuffix::class);
        new PublicSuffix('ac.be', 'foobar');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException()
    {
        $this->expectException(InvalidDomain::class);
        (new PublicSuffix('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @covers ::toAscii
     * @covers ::toUnicode
     * @covers ::idnToAscii
     * @covers ::idnToUnicode
     *
     * @dataProvider conversionReturnsTheSameInstanceProvider
     *
     * @param string|null $publicSuffix
     */
    public function testConversionReturnsTheSameInstance($publicSuffix)
    {
        $instance = new PublicSuffix($publicSuffix);
        $this->assertSame($instance->toUnicode(), $instance);
        $this->assertSame($instance->toAscii(), $instance);
    }

    public function conversionReturnsTheSameInstanceProvider()
    {
        return [
            'ascii only domain' => ['ac.be'],
            'null domain' => [null],
        ];
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
