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
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

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
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertSame(['be', 'ac'], iterator_to_array($publicSuffix));
        self::assertJsonStringEqualsJsonString(
            json_encode($publicSuffix->__debugInfo()),
            json_encode($publicSuffix)
        );
        self::assertSame('ac.be', (string) $publicSuffix);
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::setSection
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testPSToUnicodeWithUrlEncode()
    {
        self::assertSame('bébe', (new PublicSuffix('b%C3%A9be'))->toUnicode()->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::parse
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
        self::assertSame($isKnown, $ps->isKnown());
        self::assertSame($isIcann, $ps->isICANN());
        self::assertSame($isPrivate, $ps->isPrivate());
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
     * @covers ::parse
     * @covers ::setPublicSuffix
     * @dataProvider invalidPublicSuffixProvider
     *
     * @param mixed $publicSuffix
     */
    public function testConstructorThrowsException($publicSuffix)
    {
        self::expectException(InvalidDomain::class);
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
     * @covers ::parse
     * @covers ::idnToAscii
     */
    public function testPSToAsciiThrowsException()
    {
        self::expectException(InvalidDomain::class);
        new PublicSuffix('a⒈com');
    }

    /**
     * @covers ::__construct
     * @covers ::setSection
     */
    public function testSetSectionThrowsException()
    {
        self::expectException(CouldNotResolvePublicSuffix::class);
        new PublicSuffix('ac.be', 'foobar');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException()
    {
        self::expectException(InvalidDomain::class);
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
        self::assertSame($instance->toUnicode(), $instance);
        self::assertSame($instance->toAscii(), $instance);
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
        self::assertSame($instance->toUnicode(), $instance);
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
        self::assertCount($nbLabels, $domain);
        self::assertSame($labels, iterator_to_array($domain));
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
        self::assertSame('com', $domain->getLabel(0));
        self::assertSame('example', $domain->getLabel(1));
        self::assertSame('master', $domain->getLabel(-1));
        self::assertNull($domain->getLabel(23));
        self::assertNull($domain->getLabel(-23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $domain = new PublicSuffix('master.example.com');
        self::assertSame([2], $domain->keys('master'));
    }

    /**
     * @covers ::labels
     */
    public function testLabels()
    {
        $publicSuffix = new PublicSuffix('master.example.com');
        self::assertSame([
            'com',
            'example',
            'master',
        ], $publicSuffix->labels());

        $publicSuffix = new PublicSuffix();
        self::assertSame([], $publicSuffix->labels());
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
        self::assertSame($expected, $result->getContent());
        self::assertSame($result->isKnown(), $domain->isKnown());
        self::assertSame($result->isICANN(), $domain->isICANN());
        self::assertSame($result->isPrivate(), $domain->isPrivate());
        self::assertSame(
            [$result->getAsciiIDNAOption(), $result->getUnicodeIDNAOption()],
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()]
        );
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
            [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('be', Rules::ICANN_DOMAINS), IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE),
                'expected' => 'be',
            ],
        ];
    }
    
    /**
     * @covers ::isTransitionalDifferent
     *
     * @dataProvider customIDNAProvider
     *
     * @param string $name
     * @param string $expectedContent
     * @param string $expectedAscii
     * @param string $expectedUnicode
     */
    public function testResolveWithCustomIDNAOptions(
        string $name,
        string $expectedContent,
        string $expectedAscii,
        string $expectedUnicode
    ) {
        $publicSuffix = new PublicSuffix($name, '', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
        self::assertSame($expectedContent, $publicSuffix->getContent());
        self::assertSame($expectedAscii, $publicSuffix->toAscii()->getContent());
        self::assertSame($expectedUnicode, $publicSuffix->toUnicode()->getContent());
        $instance = $publicSuffix->toUnicode();
        self::assertSame(
            [$publicSuffix->getAsciiIDNAOption(), $publicSuffix->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );
    }
    
    public function customIDNAProvider()
    {
        return [
            'without deviation characters' => [
                'example.com',
                'example.com',
                'example.com',
                'example.com',
            ],
            'without deviation characters with label' => [
                'www.example.com',
                'www.example.com',
                'www.example.com',
                'www.example.com',
            ],
            'with deviation in domain' => [
                'www.faß.de',
                'www.faß.de',
                'www.xn--fa-hia.de',
                'www.faß.de',
            ],
            'with deviation in label' => [
                'faß.test.de',
                'faß.test.de',
                'xn--fa-hia.test.de',
                'faß.test.de',
            ],
        ];
    }
    
    /**
     * @covers ::isTransitionalDifferent
     *
     * @dataProvider transitionalProvider
     * @param \Pdp\PublicSuffix $publicSuffix
     * @param bool              $expected
     */
    public function testIsTransitionalDifference(PublicSuffix $publicSuffix, bool $expected)
    {
        self::assertSame($expected, $publicSuffix->isTransitionalDifferent());
    }
    
    public function transitionalProvider()
    {
        return [
            'simple' => [new PublicSuffix('example.com'), false],
            'idna' => [new PublicSuffix('français.fr'), false],
            'in domain' => [new PublicSuffix('faß.de'), true],
            'in domain 2' => [new PublicSuffix('βόλος.com'), true],
            'in domain 3' => [new PublicSuffix('ශ්‍රී.com'), true],
            'in domain 4' => [new PublicSuffix('نامه‌ای.com'), true],
            'in label' => [new PublicSuffix('faß.test.de'), true],
        ];
    }

    /**
     * @covers ::getAsciiIDNAOption
     * @covers ::getUnicodeIDNAOption
     * @covers ::withAsciiIDNAOption
     * @covers ::withUnicodeIDNAOption
     */
    public function testwithIDNAOptions()
    {
        $publicSuffix = new PublicSuffix('com');

        self::assertSame($publicSuffix, $publicSuffix->withAsciiIDNAOption(
            $publicSuffix->getAsciiIDNAOption()
        ));

        self::assertNotEquals($publicSuffix, $publicSuffix->withAsciiIDNAOption(
            IDNA_NONTRANSITIONAL_TO_ASCII
        ));

        self::assertSame($publicSuffix, $publicSuffix->withUnicodeIDNAOption(
            $publicSuffix->getUnicodeIDNAOption()
        ));

        self::assertNotEquals($publicSuffix, $publicSuffix->withUnicodeIDNAOption(
            IDNA_NONTRANSITIONAL_TO_UNICODE
        ));
    }
}
