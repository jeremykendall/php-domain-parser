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
    public function testInternalPhpMethod(): void
    {
        $publicSuffix = new PublicSuffix('ac.be');
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertSame(['be', 'ac'], iterator_to_array($publicSuffix));
        self::assertEquals($publicSuffix->__debugInfo(), $publicSuffix->jsonSerialize());
        self::assertSame('ac.be', (string) $publicSuffix);
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::setSection
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testPSToUnicodeWithUrlEncode(): void
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
     * @param ?string $publicSuffix
     * @param string  $section
     * @param bool    $isKnown
     * @param bool    $isIcann
     * @param bool    $isPrivate
     */
    public function testSetSection(?string $publicSuffix, string $section, bool $isKnown, bool $isIcann, bool $isPrivate): void
    {
        $ps = new PublicSuffix($publicSuffix, $section);
        self::assertSame($isKnown, $ps->isKnown());
        self::assertSame($isIcann, $ps->isICANN());
        self::assertSame($isPrivate, $ps->isPrivate());
    }

    public function PSProvider(): iterable
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
    public function testConstructorThrowsException($publicSuffix): void
    {
        self::expectException(InvalidDomain::class);
        new PublicSuffix($publicSuffix);
    }

    public function invalidPublicSuffixProvider(): iterable
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
    public function testPSToAsciiThrowsException(): void
    {
        self::expectException(InvalidDomain::class);
        new PublicSuffix('a⒈com');
    }

    /**
     * @covers ::__construct
     * @covers ::setSection
     */
    public function testSetSectionThrowsException(): void
    {
        self::expectException(CouldNotResolvePublicSuffix::class);
        new PublicSuffix('ac.be', 'foobar');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException(): void
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
     * @param ?string $publicSuffix
     */
    public function testConversionReturnsTheSameInstance(?string $publicSuffix): void
    {
        $instance = new PublicSuffix($publicSuffix);
        self::assertSame($instance->toUnicode(), $instance);
        self::assertSame($instance->toAscii(), $instance);
    }

    public function conversionReturnsTheSameInstanceProvider(): iterable
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
    public function testToUnicodeReturnsSameInstance(): void
    {
        $instance = new PublicSuffix('食狮.公司.cn');
        self::assertSame($instance->toUnicode(), $instance);
    }

    /**
     * @covers ::count
     * @dataProvider countableProvider
     * @param ?string $domain
     * @param int     $nbLabels
     * @param array   $labels
     */
    public function testCountable(?string $domain, int $nbLabels, array $labels): void
    {
        $domain = new PublicSuffix($domain);
        self::assertCount($nbLabels, $domain);
        self::assertSame($labels, iterator_to_array($domain));
    }

    public function countableProvider(): iterable
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
    public function testGetLabel(): void
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
    public function testOffsets(): void
    {
        $domain = new PublicSuffix('master.example.com');
        self::assertSame([2], $domain->keys('master'));
    }

    /**
     * @covers ::labels
     */
    public function testLabels(): void
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
     * @param Domain  $domain
     * @param ?string $expected
     */
    public function testCreateFromDomainWorks(Domain $domain, ?string $expected): void
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

    public function createFromDomainProvider(): iterable
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
    ): void {
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

    public function customIDNAProvider(): iterable
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
     * @param PublicSuffix $publicSuffix
     * @param bool         $expected
     */
    public function testIsTransitionalDifference(PublicSuffix $publicSuffix, bool $expected): void
    {
        self::assertSame($expected, $publicSuffix->isTransitionalDifferent());
    }

    public function transitionalProvider(): iterable
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
    public function testwithIDNAOptions(): void
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
